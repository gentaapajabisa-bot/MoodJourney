<?php
/**
 * Cadangan lengkap: catatan (CSV per tahun), surat, dan semua foto dalam beberapa berkas ZIP.
 * Vercel membatasi respons sekitar 4,5 MB, jadi cadangan dibagi menjadi bagian-bagian <= 3,2 MB.
 */
$user = require_member();
$userId = (int) $user["id"];

const BACKUP_PART_BYTES = 3200000;

function backup_csv(array $rows): string
{
    $safe = function (string $v): string {
        return $v !== "" && in_array($v[0], ["=", "+", "-", "@", "\t", "\r"], true) ? "'" . $v : $v;
    };
    $fh = fopen("php://temp", "w+");
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ["Tanggal", "Mood", "Nama mood", "Skor (1-5)", "Intensitas (1-5)", "Aktivitas", "Tag", "Cerita"], ";");
    foreach ($rows as $e) {
        $tagNames = array_map(fn($t) => TAGS[$t] ?? $t, tags_to_array($e["tags"] ?? ""));
        fputcsv($fh, [
            $e["diary_date"], $e["mood"], mood_label($e["mood"]), (int) mood_score($e["mood"]), (int) $e["intensity"],
            $safe((string) $e["activity"]), implode(", ", $tagNames), $safe(str_replace(["\r\n", "\r"], "\n", (string) $e["note"])),
        ], ";");
    }
    rewind($fh);
    $csv = (string) stream_get_contents($fh);
    fclose($fh);
    return $csv;
}

/** Susun daftar isi cadangan: [["name","size","kind","ref"], ...] */
function backup_items(int $userId): array
{
    $items = [];

    $byYear = [];
    foreach (db_all("SELECT mood, intensity, tags, activity, note, diary_date FROM diary WHERE user_id = ? ORDER BY diary_date ASC, id ASC", "i", [$userId]) as $e) {
        $byYear[substr($e["diary_date"], 0, 4)][] = $e;
    }
    foreach ($byYear as $year => $rows) {
        $csv = backup_csv($rows);
        $items[] = ["name" => "catatan/catatan-$year.csv", "size" => strlen($csv), "kind" => "text", "ref" => $csv];
    }

    $letters = db_all("SELECT body, open_on, created_at, opened_at FROM letters WHERE user_id = ? ORDER BY open_on ASC", "i", [$userId]);
    if ($letters) {
        $txt = "";
        foreach ($letters as $l) {
            $txt .= "Ditulis: " . substr($l["created_at"], 0, 10) . " | Terbuka: " . $l["open_on"] . "\n" . $l["body"] . "\n\n----\n\n";
        }
        $items[] = ["name" => "surat-untuk-diri-sendiri.txt", "size" => strlen($txt), "kind" => "text", "ref" => $txt];
    }

    $photos = db_all(
        "SELECT p.id, p.diary_id, p.mime, p.size, d.diary_date FROM diary_photos p JOIN diary d ON d.id = p.diary_id
         WHERE p.user_id = ? ORDER BY d.diary_date ASC, p.id ASC",
        "i",
        [$userId]
    );
    $n = [];
    foreach ($photos as $p) {
        $n[$p["diary_id"]] = ($n[$p["diary_id"]] ?? 0) + 1;
        $ext = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"][$p["mime"]] ?? "bin";
        $items[] = [
            "name" => "foto/" . $p["diary_date"] . "_catatan" . $p["diary_id"] . "_" . $n[$p["diary_id"]] . "." . $ext,
            "size" => (int) $p["size"], "kind" => "photo", "ref" => (int) $p["id"],
        ];
    }
    return $items;
}

/** Bagi item ke bagian-bagian. Bagian 1 menyisakan ruang untuk README. */
function backup_parts(array $items): array
{
    $parts = [[]];
    $used = 4000; // README
    foreach ($items as $it) {
        if ($used + $it["size"] > BACKUP_PART_BYTES && $parts[count($parts) - 1]) {
            $parts[] = [];
            $used = 0;
        }
        $parts[count($parts) - 1][] = $it;
        $used += $it["size"] + 120;
    }
    return $parts;
}

$items = backup_items($userId);
$parts = backup_parts($items);
$total = count($parts);

$part = (int) ($_GET["part"] ?? 0);
if ($part >= 1) {
    if ($part > $total) {
        redirect("/cadangan.php");
    }
    if (rate_count("backup:" . $userId, 3600) >= 20) {
        flash("error", "Terlalu sering mengunduh. Coba lagi nanti.");
        redirect("/cadangan.php");
    }
    rate_hit("backup:" . $userId);

    $files = [];
    if ($part === 1) {
        $files["BACA-DULU.txt"] = "Cadangan MoodJourney milik " . $user["name"] . "\nDibuat: " . date("Y-m-d H:i") . "\n\n"
            . "Isi:\n- catatan/ : catatan per tahun (CSV, buka dengan Excel atau Google Sheets, pemisah titik koma)\n"
            . "- surat-untuk-diri-sendiri.txt : suratmu (jika ada)\n- foto/ : foto catatan\n\n"
            . "Cadangan ini terdiri dari " . $total . " bagian (ZIP). Unduh semua bagian agar lengkap.\n"
            . "Jaga berkas ini baik-baik: isinya pribadi.\n";
    }
    foreach ($parts[$part - 1] as $it) {
        if ($it["kind"] === "text") {
            $files[$it["name"]] = $it["ref"];
        } else {
            $row = db_one("SELECT data FROM diary_photos WHERE id = ? AND user_id = ?", "ii", [$it["ref"], $userId]);
            if ($row) {
                $files[$it["name"]] = $row["data"];
            }
        }
    }
    $zip = zip_build($files);
    header("Content-Type: application/zip");
    header('Content-Disposition: attachment; filename="moodjourney-cadangan-' . $part . '-dari-' . $total . '.zip"');
    header("Content-Length: " . strlen($zip));
    echo $zip;
    exit;
}

$photoCount = count(array_filter($items, fn($i) => $i["kind"] === "photo"));
$entryCount = (int) db_value("SELECT COUNT(*) FROM diary WHERE user_id = ?", "i", [$userId], 0);

page_header("Cadangan", ["active" => "profile"]);
?>
<div class="container narrow">
    <section class="card">
        <h1>Cadangan lengkap</h1>
        <p class="muted">Unduh semua catatan, surat, dan fotomu. Berkas dibuat saat kamu menekan tombol unduh dan tidak disimpan di server.</p>

        <div class="grid-3 yip-stats">
            <div class="stat-card"><span class="muted">Catatan</span><span class="number"><?= $entryCount ?></span></div>
            <div class="stat-card"><span class="muted">Foto</span><span class="number"><?= $photoCount ?></span></div>
            <div class="stat-card"><span class="muted">Bagian ZIP</span><span class="number"><?= $total ?></span></div>
        </div>

        <ul class="adm-list">
            <?php foreach ($parts as $i => $its):
                $bytes = array_sum(array_column($its, "size")); ?>
                <li>
                    <span>Bagian <?= $i + 1 ?> dari <?= $total ?>
                        <small class="muted"><?= count($its) ?> berkas · sekitar <?= number_format(max(1, $bytes) / 1000000, 1) ?> MB<?= $i === 0 ? " · termasuk catatan dan surat" : "" ?></small></span>
                    <a class="btn btn-small" href="/cadangan.php?part=<?= $i + 1 ?>">Unduh</a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="muted card-foot">Dibagi beberapa bagian karena ukuran unduhan per permintaan dibatasi. Unduh semua bagian agar cadanganmu lengkap.</p>
        <p><a class="btn btn-outline btn-small" href="/profile.php#datamu">← Kembali ke Profil</a></p>
    </section>
</div>
<?php page_footer();
