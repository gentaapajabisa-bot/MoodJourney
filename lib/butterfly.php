<?php
/**
 * Perjalanan metamorfosis: streak harian yang divisualkan sebagai kupu-kupu.
 *
 * Seluruh status DIHITUNG dari tabel diary (tanpa tabel baru), jadi selalu konsisten
 * walau catatan diubah, dihapus, atau diisi untuk tanggal lampau.
 *
 * Aturan:
 *  - Setiap hari ada catatan  : perjalanan +1 hari.
 *  - Satu hari terlewat per minggu (Senin-Minggu) dimaafkan ("hari angin"): tidak menambah, tidak mengurangi.
 *  - Hari terlewat berikutnya di minggu yang sama: perjalanan turun separuh (bukan nol),
 *    paling banyak satu kali per minggu, jadi absen seminggu penuh tidak menghapus semuanya.
 *  - Hari ini yang belum dicatat belum dianggap terlewat.
 *  - Setelah turun dari tahap kepompong ke atas, kupu-kupu "beristirahat" di kepompong sampai pengguna mencatat lagi.
 */

/** Ambang hari untuk tiap tahap, berurutan. */
const BUTTERFLY_STAGES = [
    "egg"   => ["min" => 0,  "label" => "Telur",           "tagline" => "Awal perjalanan",
                "text" => "Semua kupu-kupu mulai dari sesuatu yang kecil. Satu catatan sudah cukup untuk menjaganya tetap hangat."],
    "larva" => ["min" => 3,  "label" => "Ulat",            "tagline" => "Mulai tumbuh",
                "text" => "Telurmu sudah menetas. Pelan-pelan kamu mengenali dirimu lewat catatan harian."],
    "pupa"  => ["min" => 7,  "label" => "Kepompong",       "tagline" => "Berubah dalam diam",
                "text" => "Dari luar tampak tenang, tapi di dalam banyak yang sedang berubah. Kebiasaan ini sedang membentukmu."],
    "young" => ["min" => 14, "label" => "Kupu-kupu muda",  "tagline" => "Sayap baru",
                "text" => "Kamu baru keluar dari kepompong. Sayapnya masih belajar mengepak, dan itu sudah cukup indah."],
    "adult" => ["min" => 30, "label" => "Kupu-kupu dewasa", "tagline" => "Terbang bebas",
                "text" => "Sebulan lebih kamu setia pada dirimu sendiri. Sayapmu sudah penuh."],
];

/** Warna sayap mengikuti mood paling sering dicatat: [warna utama, warna kedua]. */
const BUTTERFLY_PALETTE = [
    "😄" => ["#f6b73c", "#ff8a3d"],
    "🥰" => ["#f58ab0", "#c85cd6"],
    "🙂" => ["#4cc3a0", "#3aa0d8"],
    "😐" => ["#9a8cff", "#6d5ce8"],
    "😴" => ["#6aa7f0", "#8a7cf0"],
    "😰" => ["#b07ee8", "#7a5ad8"],
    "😔" => ["#5b7fd6", "#3f5bb0"],
    "😡" => ["#ef7a5a", "#d6455d"],
];
const BUTTERFLY_DEFAULT_COLORS = ["#9a8cff", "#6d5ce8"];

/** Kunci tahap untuk jumlah hari tertentu. */
function butterfly_stage_for(int $days): string
{
    $key = "egg";
    foreach (BUTTERFLY_STAGES as $k => $s) {
        if ($days >= $s["min"]) {
            $key = $k;
        }
    }
    return $key;
}

/**
 * Hitung status perjalanan dari daftar tanggal catatan.
 *
 * @param string[]    $dates tanggal unik (Y-m-d), urutan bebas
 * @param string|null $today untuk pengujian; default hari ini
 */
function journey_compute(array $dates, ?string $today = null): array
{
    $today = $today ?? date("Y-m-d");
    $dates = array_values(array_unique(array_filter($dates, fn($d) => is_string($d) && $d <= $today)));
    $set = array_flip($dates);

    $days = 0;
    $graceWeek = null;
    $fallWeek = null;
    $lastFall = null;
    $fallFrom = 0;
    $loggedSinceFall = false;
    $graceDay = null;

    if ($dates) {
        $day = new DateTimeImmutable(min($dates));
        $end = new DateTimeImmutable($today);
        for (; $day <= $end; $day = $day->modify("+1 day")) {
            $d = $day->format("Y-m-d");
            if (isset($set[$d])) {
                $days++;
                if ($lastFall !== null) {
                    $loggedSinceFall = true;
                }
                continue;
            }
            if ($d === $today) {
                continue; // hari ini masih berjalan
            }
            $week = week_start($d);
            if ($graceWeek !== $week) {
                $graceWeek = $week; // hari angin minggu ini
                $graceDay = $d;
                continue;
            }
            if ($fallWeek === $week) {
                continue; // sudah turun di minggu ini
            }
            $fallWeek = $week;
            $before = $days;
            $days = intdiv($days, 2);
            if ($before > $days) {
                $lastFall = $d;
                $fallFrom = $before;
                $loggedSinceFall = false;
            }
        }
    }

    $stage = butterfly_stage_for($days);
    $resting = $lastFall !== null && !$loggedSinceFall && $fallFrom >= BUTTERFLY_STAGES["pupa"]["min"];
    $display = $resting ? "pupa" : $stage;

    $keys = array_keys(BUTTERFLY_STAGES);
    $idx = array_search($stage, $keys, true);
    $next = $keys[$idx + 1] ?? null;
    if ($next !== null) {
        $from = BUTTERFLY_STAGES[$stage]["min"];
        $to = BUTTERFLY_STAGES[$next]["min"];
        $pct = (int) round(($days - $from) / ($to - $from) * 100);
        $left = $to - $days;
    } else {
        $pct = 100;
        $left = 0;
    }

    return [
        "days"            => $days,
        "stage"           => $stage,
        "display"         => $display,
        "stage_index"     => (int) $idx,
        "resting"         => $resting,
        "fell_from"       => $fallFrom,
        "today_done"      => isset($set[$today]),
        "grace_available" => $graceWeek !== week_start($today),
        "grace_day"       => $graceDay,
        "next"            => $next,
        "days_left"       => $left,
        "progress"        => max(0, min(100, $pct)),
        "total_logged"    => count($dates),
    ];
}

/** Ambil data pengguna dari database lalu hitung perjalanannya. */
function journey_load(int $userId): array
{
    $dates = array_column(
        db_all(
            "SELECT DISTINCT diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date ASC",
            "is",
            [$userId, date("Y-m-d", strtotime("-800 days"))]
        ),
        "diary_date"
    );
    $journey = journey_compute($dates);

    $top = db_one(
        "SELECT mood, COUNT(*) AS c FROM diary
         WHERE user_id = ? AND diary_date >= ?
         GROUP BY mood ORDER BY c DESC, MAX(id) DESC LIMIT 1",
        "is",
        [$userId, date("Y-m-d", strtotime("-13 days"))]
    );
    $mood = $top["mood"] ?? null;
    $journey["mood"] = $mood;
    $journey["colors"] = $mood !== null && isset(BUTTERFLY_PALETTE[$mood]) ? BUTTERFLY_PALETTE[$mood] : BUTTERFLY_DEFAULT_COLORS;
    return $journey;
}

/* ---------- Gambar SVG ---------- */

/** Sepasang sayap (kanan digambar, kiri dicerminkan). */
function butterfly_wings_markup(string $gid, bool $detailed): string
{
    $stroke = 'stroke="rgba(30,25,50,.30)" stroke-width="1.5" stroke-linejoin="round"';
    $wing = '<path d="M102 98 C 118 52, 168 24, 184 52 C 196 76, 160 104, 102 104 Z" fill="url(#' . $gid . '-w)" ' . $stroke . '/>'
          . '<path d="M102 106 C 150 106, 176 130, 160 158 C 148 176, 118 160, 102 124 Z" fill="url(#' . $gid . '-w2)" ' . $stroke . '/>';
    if ($detailed) {
        $wing .= '<circle cx="160" cy="58" r="9" fill="#fff" fill-opacity=".72"/>'
               . '<circle cx="144" cy="80" r="5" fill="#fff" fill-opacity=".6"/>'
               . '<circle cx="139" cy="141" r="7" fill="#fff" fill-opacity=".66"/>'
               . '<path d="M112 100 C 134 78, 158 66, 176 60" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="2" stroke-linecap="round"/>';
    } else {
        $wing .= '<circle cx="156" cy="62" r="6" fill="#fff" fill-opacity=".6"/>';
    }
    return '<g class="mj-wing mj-wing-r">' . $wing . '</g>'
         . '<g transform="translate(200 0) scale(-1 1)"><g class="mj-wing mj-wing-l">' . $wing . '</g></g>';
}

function butterfly_body_markup(): string
{
    return '<path d="M97 72 C 90 56, 84 50, 76 46 M103 72 C 110 56, 116 50, 124 46" fill="none" stroke="#3b3550" stroke-width="2" stroke-linecap="round"/>'
         . '<circle cx="76" cy="46" r="3" fill="#3b3550"/><circle cx="124" cy="46" r="3" fill="#3b3550"/>'
         . '<ellipse cx="100" cy="112" rx="5.5" ry="30" fill="#3b3550"/>'
         . '<circle cx="100" cy="78" r="7.5" fill="#3b3550"/>';
}

/**
 * SVG satu tahap.
 * @param string   $stage   egg | larva | pupa | young | adult
 * @param string[] $colors  [warna utama, warna kedua]
 * @param array    $opts    animate(bool), resting(bool), label(string)
 */
function butterfly_svg(string $stage, array $colors, array $opts = []): string
{
    static $n = 0;
    $gid = "bf" . (++$n);
    $animate = $opts["animate"] ?? true;
    $resting = $opts["resting"] ?? false;
    $label = $opts["label"] ?? (BUTTERFLY_STAGES[$stage]["label"] ?? "Kupu-kupu");
    [$c1, $c2] = $colors + [BUTTERFLY_DEFAULT_COLORS[0], BUTTERFLY_DEFAULT_COLORS[1]];
    $c1 = preg_match('/^#[0-9a-f]{6}$/i', (string) $c1) ? $c1 : BUTTERFLY_DEFAULT_COLORS[0];
    $c2 = preg_match('/^#[0-9a-f]{6}$/i', (string) $c2) ? $c2 : BUTTERFLY_DEFAULT_COLORS[1];

    $defs = '<defs>'
        . '<linearGradient id="' . $gid . '-w" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient>'
        . '<linearGradient id="' . $gid . '-w2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c2 . '"/><stop offset="1" stop-color="' . $c1 . '"/></linearGradient>'
        . '<linearGradient id="' . $gid . '-c" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b7e0b0"/><stop offset="1" stop-color="' . $c1 . '"/></linearGradient>'
        . '</defs>';

    $leaf = '<path d="M14 172 C 48 140, 152 140, 186 172 C 152 196, 48 196, 14 172 Z" fill="#58b274"/>'
          . '<path d="M20 172 C 60 168, 140 168, 180 172" fill="none" stroke="#3b8f58" stroke-width="2.5" stroke-linecap="round"/>';

    switch ($stage) {
        case "egg":
            $art = $leaf
                . '<g class="mj-egg">'
                . '<ellipse cx="100" cy="128" rx="31" ry="39" fill="#fff6e4" stroke="rgba(30,25,50,.25)" stroke-width="1.5"/>'
                . '<circle cx="88" cy="118" r="4" fill="' . $c1 . '" fill-opacity=".55"/>'
                . '<circle cx="110" cy="132" r="5" fill="' . $c2 . '" fill-opacity=".5"/>'
                . '<circle cx="96" cy="146" r="3.5" fill="' . $c1 . '" fill-opacity=".5"/>'
                . '<ellipse cx="89" cy="108" rx="6" ry="11" fill="#fff" fill-opacity=".6" transform="rotate(-18 89 108)"/>'
                . '</g>';
            break;

        case "larva":
            $segs = [[44, 150, 12], [64, 142, 13], [85, 137, 14], [107, 137, 14], [128, 141, 14]];
            $body = "";
            foreach ($segs as $i => [$x, $y, $r]) {
                $fill = $i % 2 === 0 ? $c1 : $c2;
                $body .= '<path d="M' . ($x - 5) . ' ' . ($y + $r - 2) . ' v6 M' . ($x + 5) . ' ' . ($y + $r - 2) . ' v6" stroke="#3b3550" stroke-width="2.2" stroke-linecap="round"/>'
                       . '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="' . $fill . '" stroke="rgba(30,25,50,.28)" stroke-width="1.5"/>';
            }
            $art = $leaf
                . '<g class="mj-larva">' . $body
                . '<circle cx="152" cy="130" r="16" fill="#ffd9a8" stroke="rgba(30,25,50,.28)" stroke-width="1.5"/>'
                . '<path d="M148 116 C 146 108, 142 104, 138 102 M158 116 C 160 108, 164 104, 168 102" fill="none" stroke="#3b3550" stroke-width="2" stroke-linecap="round"/>'
                . '<circle cx="146" cy="128" r="2.6" fill="#3b3550"/><circle cx="158" cy="128" r="2.6" fill="#3b3550"/>'
                . '<path d="M147 137 Q 152 142 157 137" fill="none" stroke="#3b3550" stroke-width="2" stroke-linecap="round"/>'
                . '</g>';
            break;

        case "pupa":
            $art = '<path d="M30 24 C 70 14, 130 14, 172 22" fill="none" stroke="#7a5a3c" stroke-width="7" stroke-linecap="round"/>'
                . '<g class="mj-pupa">'
                . '<path d="M100 20 V 54" stroke="#9a9a8a" stroke-width="2" stroke-linecap="round"/>'
                . '<path d="M100 54 C 130 62, 136 102, 121 132 C 113 150, 105 160, 100 170 C 95 160, 87 150, 79 132 C 64 102, 70 62, 100 54 Z" fill="url(#' . $gid . '-c)" stroke="rgba(30,25,50,.28)" stroke-width="1.5"/>'
                . '<path d="M76 86 C 90 94, 110 94, 124 86 M72 106 C 88 116, 112 116, 128 106 M76 128 C 90 136, 110 136, 124 128" fill="none" stroke="rgba(30,25,50,.22)" stroke-width="2" stroke-linecap="round"/>'
                . '<circle cx="104" cy="72" r="2.2" fill="#ffe08a"/><circle cx="92" cy="98" r="1.8" fill="#ffe08a"/><circle cx="108" cy="124" r="2" fill="#ffe08a"/>'
                . '</g>'
                . ($resting
                    ? '<g class="mj-zzz" fill="currentColor" font-weight="700" font-family="system-ui,sans-serif"><text x="138" y="70" font-size="16">z</text><text x="150" y="52" font-size="12">z</text><text x="160" y="38" font-size="9">z</text></g>'
                    : '');
            break;

        case "young":
            $art = '<g transform="translate(100 112) scale(.74) translate(-100 -112)">'
                . '<g class="mj-flap mj-flap-slow">' . butterfly_wings_markup($gid, false) . '</g>'
                . butterfly_body_markup() . '</g>';
            break;

        default: // adult
            $art = '<g class="mj-flyer">'
                . '<g class="mj-flap">' . butterfly_wings_markup($gid, true) . '</g>'
                . butterfly_body_markup() . '</g>'
                . '<g class="mj-sparkle" fill="' . $c1 . '"><circle cx="26" cy="40" r="2.4"/><circle cx="176" cy="30" r="2"/><circle cx="168" cy="182" r="2.6"/><circle cx="30" cy="170" r="1.8"/></g>';
            break;
    }

    return '<svg class="mj-svg mj-svg-' . h($stage) . ($animate ? "" : " is-still") . '" viewBox="0 0 200 200" role="img" aria-label="' . h($label) . '" xmlns="http://www.w3.org/2000/svg">'
        . $defs . $art . '</svg>';
}

/** Teks pendek untuk memberi tahu pengguna bahwa tahapnya naik. */
function journey_stage_up_text(string $newStage): string
{
    return "Kupu-kupumu naik tahap menjadi " . BUTTERFLY_STAGES[$newStage]["label"] . ". " . BUTTERFLY_STAGES[$newStage]["tagline"] . ".";
}

/* ---------- Latar aura pastel & animasi naik tahap ---------- */

/** Tema latar dari emoji mood: joy | good | neutral | tired | anxious | sad | angry | none. */
function aura_theme_for_mood(?string $emoji): string
{
    $map = [
        "😄" => "joy", "🥰" => "joy", "🙂" => "good", "😐" => "neutral",
        "😴" => "tired", "😰" => "anxious", "😔" => "sad", "😡" => "angry",
    ];
    return $map[(string) $emoji] ?? "none";
}

/** Jumlah kupu-kupu di taman: bertambah seiring perjalanan (1 sampai 8). */
function garden_butterfly_count(int $days): int
{
    return max(1, min(8, 1 + intdiv($days, 4)));
}

/**
 * Lapisan perayaan naik tahap: kepompong/ulat bergetar, lalu tahap baru muncul bersama kelopak.
 * $autoShow=true dipakai setelah menyimpan catatan; false untuk pratinjau (dibuka lewat tombol).
 */
function stage_up_overlay_html(string $from, string $to, array $colors, bool $autoShow, string $id = "stage-up"): string
{
    if (!isset(BUTTERFLY_STAGES[$from], BUTTERFLY_STAGES[$to])) {
        return "";
    }
    $meta = BUTTERFLY_STAGES[$to];
    ob_start();
    ?>
    <div class="stageup" id="<?= h($id) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= h($id) ?>-title"
         data-autoshow="<?= $autoShow ? "1" : "0" ?>" <?= $autoShow ? "" : "hidden" ?>>
        <div class="stageup-rays" aria-hidden="true"></div>
        <canvas class="stageup-canvas" aria-hidden="true"></canvas>
        <div class="stageup-card">
            <div class="stageup-art">
                <div class="stageup-old" aria-hidden="true"><?= butterfly_svg($from, $colors, ["animate" => false]) ?></div>
                <div class="stageup-new"><?= butterfly_svg($to, $colors, ["label" => $meta["label"]]) ?></div>
            </div>
            <p class="stageup-kicker">Kupu-kupumu naik tahap</p>
            <h2 id="<?= h($id) ?>-title"><?= h($meta["label"]) ?></h2>
            <p class="stageup-tagline"><?= h($meta["tagline"]) ?></p>
            <div class="stageup-actions">
                <button type="button" class="btn" data-stageup-close>Lanjut</button>
                <a class="btn btn-outline" href="/metamorfosis.php">Lihat perjalananku</a>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/** Warna pastel: campur dengan putih ($t 0..1). */
function butterfly_pastel(array $colors, float $t = 0.3): array
{
    return array_map(function ($hex) use ($t) {
        $hex = ltrim((string) $hex, "#");
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return BUTTERFLY_DEFAULT_COLORS[0];
        }
        $out = "#";
        foreach ([0, 2, 4] as $i) {
            $v = hexdec(substr($hex, $i, 2));
            $out .= sprintf("%02x", (int) round($v + (255 - $v) * $t));
        }
        return $out;
    }, array_slice($colors, 0, 2));
}

/** Bunga kecil untuk adegan halaman depan (SVG). Deterministik lewat $seed. */
function scene_flower_svg(int $seed): string
{
    $pal = ["#ffc2d9", "#ffe4a8", "#d6c9ff", "#bfe3ff", "#ffcfb8"];
    $c = $pal[$seed % count($pal)];
    $petals = "";
    for ($p = 0; $p < 6; $p++) {
        $a = $p / 6 * M_PI * 2;
        $petals .= '<circle cx="' . round(20 + cos($a) * 8, 1) . '" cy="' . round(16 + sin($a) * 8, 1) . '" r="6" fill="' . $c . '"/>';
    }
    return '<svg viewBox="0 0 40 90" width="40" height="90" aria-hidden="true">'
        . '<path d="M20 90 C 20 66, 22 44, 20 22" fill="none" stroke="#9fd8b4" stroke-width="3" stroke-linecap="round"/>'
        . '<path d="M20 62 C 8 58, 6 50, 8 46 C 16 46, 20 54, 20 62 Z" fill="#b7e4c6"/>'
        . $petals . '<circle cx="20" cy="16" r="4.5" fill="#ffd98a"/></svg>';
}
