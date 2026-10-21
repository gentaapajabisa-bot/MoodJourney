<?php
/**
 * Fitur tambahan: "Pada hari ini", tantangan mingguan, koleksi kupu-kupu, surat untuk diri sendiri,
 * dan langkah setelah menyimpan catatan (dipakai bersama oleh form lengkap dan check-in cepat).
 */

/* ---------- Mood berat (untuk tampilan lembut dan kartu "mau ditemani?") ---------- */

const HEAVY_MOODS = ["😔", "😰", "😡"];

function is_heavy_mood(?string $emoji): bool
{
    return $emoji !== null && in_array($emoji, HEAVY_MOODS, true);
}

/** Mood terakhir dalam 2 hari terakhir yang tergolong berat, atau null. */
function recent_heavy_mood(int $userId): ?string
{
    $row = db_one(
        "SELECT mood FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date DESC, id DESC LIMIT 1",
        "is",
        [$userId, date("Y-m-d", strtotime("-1 day"))]
    );
    return $row && is_heavy_mood($row["mood"]) ? $row["mood"] : null;
}

/* ---------- Pada hari ini ---------- */

/**
 * Catatan pada tanggal yang sama di masa lalu: 1 bulan, 3 bulan, 6 bulan, dan 1 sampai 3 tahun lalu.
 * @return array<int,array{label:string,date:string,mood:string,note:string,id:int}>
 */
function memories_today(int $userId, ?string $today = null): array
{
    $today = $today ?? date("Y-m-d");
    $base = new DateTimeImmutable($today);
    $spans = [
        "1 bulan lalu"  => "-1 month",
        "3 bulan lalu"  => "-3 months",
        "6 bulan lalu"  => "-6 months",
        "Setahun lalu"  => "-1 year",
        "2 tahun lalu"  => "-2 years",
        "3 tahun lalu"  => "-3 years",
    ];
    $dates = [];
    foreach ($spans as $label => $mod) {
        $d = $base->modify($mod);
        // tanggal 31 Maret dikurangi sebulan menjadi 3 Maret: lewati agar tidak menyesatkan
        if ((int) $d->format("j") !== (int) $base->format("j")) {
            continue;
        }
        $dates[$d->format("Y-m-d")] = $label;
    }
    if (!$dates) {
        return [];
    }
    $marks = implode(",", array_fill(0, count($dates), "?"));
    $rows = db_all(
        "SELECT id, mood, note, diary_date FROM diary WHERE user_id = ? AND diary_date IN ($marks) ORDER BY diary_date DESC, id DESC",
        "i" . str_repeat("s", count($dates)),
        array_merge([$userId], array_keys($dates))
    );
    $out = [];
    foreach ($rows as $r) {
        $d = $r["diary_date"];
        if (isset($out[$d])) {
            continue; // satu per tanggal
        }
        $out[$d] = [
            "label" => $dates[$d],
            "date"  => $d,
            "mood"  => $r["mood"],
            "note"  => trim((string) $r["note"]) !== "" ? str_limit(trim((string) $r["note"]), 140) : "",
            "id"    => (int) $r["id"],
        ];
    }
    return array_slice(array_values($out), 0, 3);
}

/* ---------- Tantangan mingguan ---------- */

/**
 * Daftar tantangan. "metric": days (hari tercatat), notes (catatan dengan cerita >= 20 huruf),
 * tags (aktivitas berbeda), tag:<kode> (hari dengan aktivitas itu), mood:<emoji> (hari dengan mood itu).
 */
const CHALLENGES = [
    ["code" => "days5",    "icon" => "📅", "title" => "Lima hari tercatat",      "text" => "Catat moodmu di 5 hari berbeda minggu ini.",              "metric" => "days",       "target" => 5],
    ["code" => "notes3",   "icon" => "✍️", "title" => "Tiga cerita",             "text" => "Tulis cerita singkat (minimal 20 huruf) di 3 catatan.",    "metric" => "notes",      "target" => 3],
    ["code" => "tags3",    "icon" => "🧭", "title" => "Tiga aktivitas berbeda",  "text" => "Tandai 3 aktivitas yang berbeda minggu ini.",             "metric" => "tags",       "target" => 3],
    ["code" => "move3",    "icon" => "🏃", "title" => "Gerakkan tubuhmu",        "text" => "Tandai Olahraga di 3 hari berbeda.",                      "metric" => "tag:olahraga", "target" => 3],
    ["code" => "metime2",  "icon" => "🛁", "title" => "Waktu untuk diri",        "text" => "Tandai Me-time di 2 hari berbeda.",                        "metric" => "tag:metime", "target" => 2],
    ["code" => "people3",  "icon" => "💞", "title" => "Bersama yang tersayang",  "text" => "Tandai Keluarga atau Teman di 3 hari berbeda.",           "metric" => "tag:keluarga,teman", "target" => 3],
    ["code" => "sleep4",   "icon" => "🌙", "title" => "Tidur yang cukup",        "text" => "Tandai Tidur cukup di 4 hari berbeda.",                   "metric" => "tag:tidur",  "target" => 4],
    ["code" => "grateful2", "icon" => "🥰", "title" => "Satu hal yang disyukuri", "text" => "Pilih mood Bersyukur di 2 hari berbeda.",                "metric" => "mood:🥰",    "target" => 2],
];

/** Tantangan untuk minggu yang memuat $date (berputar tiap minggu). */
function challenge_for_week(string $weekStart): array
{
    $idx = intdiv((int) (strtotime($weekStart) / 86400), 7) % count(CHALLENGES);
    return CHALLENGES[$idx];
}

/**
 * Kemajuan tantangan minggu ini.
 * @return array{def:array,progress:int,target:int,done:bool,week_start:string,week_end:string,days_left:int}
 */
function challenge_current(int $userId, ?string $today = null): array
{
    $today = $today ?? date("Y-m-d");
    $ws = week_start($today);
    $we = week_end($ws);
    $def = challenge_for_week($ws);

    $rows = db_all(
        "SELECT mood, tags, note, diary_date FROM diary WHERE user_id = ? AND diary_date BETWEEN ? AND ?",
        "iss",
        [$userId, $ws, $we]
    );

    $metric = $def["metric"];
    $set = [];
    foreach ($rows as $r) {
        $d = $r["diary_date"];
        $tags = tags_to_array($r["tags"] ?? "");
        if ($metric === "days") {
            $set[$d] = true;
        } elseif ($metric === "notes") {
            if (mb_strlen(trim((string) $r["note"])) >= 20) {
                $set[$r["diary_date"] . "#" . count($set)] = true;
            }
        } elseif ($metric === "tags") {
            foreach ($tags as $t) {
                $set[$t] = true;
            }
        } elseif (str_starts_with($metric, "tag:")) {
            if (array_intersect($tags, explode(",", substr($metric, 4)))) {
                $set[$d] = true;
            }
        } elseif (str_starts_with($metric, "mood:")) {
            if ($r["mood"] === substr($metric, 5)) {
                $set[$d] = true;
            }
        }
    }
    $progress = min(count($set), (int) $def["target"]);
    $done = $progress >= (int) $def["target"];
    $daysLeft = max(0, (int) floor((strtotime($we) - strtotime($today)) / 86400));

    return [
        "def" => $def, "progress" => $progress, "target" => (int) $def["target"], "done" => $done,
        "week_start" => $ws, "week_end" => $we, "days_left" => $daysLeft,
    ];
}

/** Catat tantangan yang selesai (sekali per minggu). @return bool true kalau baru selesai */
function challenge_sync(int $userId): bool
{
    $c = challenge_current($userId);
    if (!$c["done"]) {
        return false;
    }
    [$affected] = db_exec(
        "INSERT IGNORE INTO challenge_done (user_id, week_start, code, done_on) VALUES (?, ?, ?, ?)",
        "isss",
        [$userId, $c["week_start"], $c["def"]["code"], date("Y-m-d")]
    );
    return $affected > 0;
}

/** Tantangan yang pernah diselesaikan, terbaru dulu. */
function challenge_history(int $userId, int $limit = 24): array
{
    $out = [];
    foreach (db_all("SELECT week_start, code, done_on FROM challenge_done WHERE user_id = ? ORDER BY week_start DESC LIMIT " . (int) $limit, "i", [$userId]) as $r) {
        foreach (CHALLENGES as $def) {
            if ($def["code"] === $r["code"]) {
                $out[] = $def + ["week_start" => $r["week_start"], "done_on" => $r["done_on"]];
                break;
            }
        }
    }
    return $out;
}

/* ---------- Koleksi kupu-kupu ---------- */

/** Jenis kupu-kupu menurut mood dominan: emoji => nama. */
const BUTTERFLY_SPECIES = [
    "😄" => "Kupu Mentari",
    "🥰" => "Kupu Mawar",
    "🙂" => "Kupu Zamrud",
    "😐" => "Kupu Lavender",
    "😴" => "Kupu Awan",
    "😰" => "Kupu Anggrek",
    "😔" => "Kupu Samudra",
    "😡" => "Kupu Bara",
];

function species_name(?string $emoji): string
{
    return BUTTERFLY_SPECIES[(string) $emoji] ?? "Kupu Perjalanan";
}

/**
 * Simpan kupu-kupu ke koleksi saat mencapai tahap muda atau dewasa.
 * @return bool true kalau ada yang baru ditambahkan
 */
function collection_record(int $userId, array $journey): bool
{
    $stage = $journey["stage"] ?? "egg";
    if (!in_array($stage, ["young", "adult"], true)) {
        return false;
    }
    $mood = $journey["mood"] ?? null;
    $colors = $journey["colors"] ?? BUTTERFLY_DEFAULT_COLORS;

    $add = function (string $st) use ($userId, $mood, $colors): bool {
        $has = (int) db_value("SELECT COUNT(*) FROM butterfly_collection WHERE user_id = ? AND stage = ? AND mood <=> ?", "iss", [$userId, $st, $mood], 0);
        if ($has > 0) {
            return false;
        }
        [$affected] = db_exec(
            "INSERT IGNORE INTO butterfly_collection (user_id, stage, mood, c1, c2, earned_on) VALUES (?, ?, ?, ?, ?, ?)",
            "isssss",
            [$userId, $st, $mood, $colors[0], $colors[1], date("Y-m-d")]
        );
        return $affected > 0;
    };

    $new = $add($stage);
    // pengguna yang langsung dewasa (data lama) mendapat juga kupu-kupu mudanya
    if ($stage === "adult" && (int) db_value("SELECT COUNT(*) FROM butterfly_collection WHERE user_id = ? AND stage = 'young'", "i", [$userId], 0) === 0) {
        $add("young");
    }
    return $new;
}

function collection_all(int $userId): array
{
    return db_all("SELECT id, stage, mood, c1, c2, earned_on FROM butterfly_collection WHERE user_id = ? ORDER BY earned_on DESC, id DESC", "i", [$userId]);
}

/* ---------- Surat untuk diri sendiri ---------- */

const LETTER_MAX_CHARS = 2000;
const LETTER_MAX_PENDING = 20;

/** Surat yang sudah waktunya dibuka tapi belum dibaca. */
function letters_ready_count(int $userId): int
{
    return (int) db_value("SELECT COUNT(*) FROM letters WHERE user_id = ? AND open_on <= ? AND opened_at IS NULL", "is", [$userId, date("Y-m-d")], 0);
}

/* ---------- Setelah menyimpan catatan ---------- */

/**
 * Langkah yang sama setelah catatan tersimpan: animasi naik tahap, koleksi, pencapaian, tantangan,
 * dan sapaan lembut kalau moodnya berat.
 */
function diary_after_save(int $userId, string $stageBefore, string $mood): void
{
    $journeyAfter = journey_load($userId);
    $stageAfter = $journeyAfter["stage"];
    $keys = array_keys(BUTTERFLY_STAGES);
    if (array_search($stageAfter, $keys, true) > array_search($stageBefore, $keys, true)) {
        $_SESSION["stage_up"] = ["from" => $stageBefore, "to" => $stageAfter, "colors" => $journeyAfter["colors"]];
    }

    $collected = collection_record($userId, $journeyAfter);
    if ($collected) {
        flash("info", "Kupu-kupu baru masuk ke koleksimu: " . species_name($journeyAfter["mood"] ?? null) . ". 🦋");
    }

    $new = check_achievements($userId);
    if ($new) {
        flash("info", achievements_flash_text($new));
    }

    if (challenge_sync($userId)) {
        $c = challenge_current($userId);
        flash("info", "Tantangan minggu ini selesai: " . $c["def"]["title"] . " " . $c["def"]["icon"]);
    }

    if (is_heavy_mood($mood)) {
        flash("info", "Terima kasih sudah jujur pada perasaanmu. Kalau mau ditemani, ada latihan napas singkat di Tenang.");
    }
}
