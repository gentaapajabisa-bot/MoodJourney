<?php
$admin = require_admin();

/* Aksi: tes AI dan buat kunci VAPID (hasilnya hanya ditampilkan di halaman ini sekali) */
$aiTest = null;
$vapid = null;
if (is_post()) {
    csrf_verify("/admin/status.php");
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "test_ai") {
        $key = (string) env_value("ANTHROPIC_API_KEY", "");
        if ($key === "") {
            $aiTest = ["state" => "fail", "text" => "ANTHROPIC_API_KEY belum diisi."];
        } else {
            $payload = json_encode([
                "model" => (string) env_value("AI_MODEL", "claude-haiku-5-5"),
                "max_tokens" => 16,
                "messages" => [["role" => "user", "content" => "Balas satu kata: siap"]],
            ]);
            $ch = curl_init("https://api.anthropic.com/v1/messages");
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ["content-type: application/json", "x-api-key: " . $key, "anthropic-version: 2023-06-01"],
            ]);
            $t0 = microtime(true);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if ($code === 200) {
                $aiTest = ["state" => "ok", "text" => "AI menjawab normal (" . $ms . " ms)."];
            } else {
                $msg = "";
                $j = is_string($resp) ? json_decode($resp, true) : null;
                if (is_array($j) && isset($j["error"]["message"])) {
                    $msg = " " . mb_substr((string) $j["error"]["message"], 0, 160);
                }
                $aiTest = ["state" => "fail", "text" => "Gagal: HTTP $code. $err$msg"];
            }
        }
    }

    if ($action === "gen_vapid") {
        $vapid = vapid_generate();
    }
}

$checks = [];
function status_row(array &$checks, string $group, string $label, string $state, string $detail): void
{
    $checks[$group][] = ["label" => $label, "state" => $state, "detail" => $detail];
}

/* Database */
$t0 = microtime(true);
$version = (string) db_value("SELECT VERSION()", "", [], "?");
$ms = (int) round((microtime(true) - $t0) * 1000);
status_row($checks, "Database", "Koneksi", $ms < 800 ? "ok" : "warn", "Terhubung, balasan $ms ms · $version");
$schema = (int) db_value("SELECT v FROM app_meta WHERE k = 'schema_version'", "", [], 0);
status_row($checks, "Database", "Versi skema", $schema >= SCHEMA_VERSION ? "ok" : "warn", "Terpasang $schema, dibutuhkan " . SCHEMA_VERSION);
$dbBytes = (int) db_value("SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()", "", [], 0);
status_row($checks, "Database", "Ukuran data", "ok", number_format($dbBytes / 1000000, 1) . " MB");

/* Penyimpanan foto */
$photoRow = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(size), 0) AS bytes FROM diary_photos") ?: ["n" => 0, "bytes" => 0];
$avatarRow = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(size), 0) AS bytes FROM user_avatars") ?: ["n" => 0, "bytes" => 0];
status_row($checks, "Penyimpanan", "Foto catatan", "ok", (int) $photoRow["n"] . " foto · " . number_format($photoRow["bytes"] / 1000000, 1) . " MB");
status_row($checks, "Penyimpanan", "Foto profil", "ok", (int) $avatarRow["n"] . " foto · " . number_format($avatarRow["bytes"] / 1000000, 1) . " MB");

/* Server */
status_row($checks, "Server", "PHP", version_compare(PHP_VERSION, "8.1", ">=") ? "ok" : "warn", PHP_VERSION);
foreach (["mysqli" => true, "curl" => true, "mbstring" => false, "openssl" => true, "gd" => false] as $ext => $needed) {
    $has = extension_loaded($ext);
    status_row($checks, "Server", "Ekstensi $ext", $has ? "ok" : ($needed ? "fail" : "warn"), $has ? "Tersedia" : ($needed ? "Tidak ada (dibutuhkan)" : "Tidak ada (cadangan dipakai)"));
}
status_row($checks, "Server", "Mode debug", (string) env_value("APP_DEBUG", "0") === "1" ? "warn" : "ok", (string) env_value("APP_DEBUG", "0") === "1" ? "APP_DEBUG=1: matikan di produksi" : "Mati");

/* Layanan */
$hasKey = (string) env_value("ANTHROPIC_API_KEY", "") !== "";
status_row($checks, "Layanan", "Asisten AI", $hasKey ? "ok" : "warn", $hasKey ? "Kunci terpasang, model " . (string) env_value("AI_MODEL", "claude-haiku-5-5") . ". Pakai tombol tes di bawah untuk memastikan." : "Tidak aktif (bot memakai panduan saja)");
$subs = (int) db_value("SELECT COUNT(*) FROM push_subscriptions", "", [], 0);
status_row($checks, "Layanan", "Notifikasi push", push_enabled() ? "ok" : "warn", push_enabled() ? $subs . " perangkat berlangganan" : "Kunci VAPID belum diisi");
status_row($checks, "Layanan", "Email pengingat", mail_enabled() ? "ok" : "warn", mail_enabled() ? "Aktif" : "Belum dikonfigurasi");
$cronSecret = (string) env_value("CRON_SECRET", "") !== "";
$cronLast = (string) db_value("SELECT v FROM app_meta WHERE k = 'cron_last'", "", [], "");
status_row($checks, "Layanan", "Pengingat harian (cron)", $cronSecret ? ($cronLast !== "" ? "ok" : "warn") : "warn",
    !$cronSecret ? "CRON_SECRET belum diisi" : ($cronLast !== "" ? "Terakhir berjalan " . $cronLast : "Belum pernah berjalan"));

/* Data */
foreach (["users" => "Akun", "diary" => "Catatan", "letters" => "Surat", "butterfly_collection" => "Koleksi kupu-kupu", "messages" => "Pesan chat"] as $tbl => $label) {
    status_row($checks, "Data", $label, "ok", number_format((int) db_value("SELECT COUNT(*) FROM `$tbl`", "", [], 0)) . " baris");
}

$all = array_merge(...array_values($checks));
$fails = count(array_filter($all, fn($c) => $c["state"] === "fail"));
$warns = count(array_filter($all, fn($c) => $c["state"] === "warn"));
$overall = $fails ? "fail" : ($warns ? "warn" : "ok");
$icons = ["ok" => "✓", "warn" => "!", "fail" => "✕"];

page_header("Status", ["nav" => "admin", "active" => "admin-status"]);
?>
<div class="container">

    <section class="adm-hero">
        <div class="adm-hero-text">
            <span class="adm-eyebrow">Status sistem</span>
            <h1><?= $overall === "ok" ? "Semuanya berjalan baik" : ($overall === "warn" ? "Ada hal yang perlu dilihat" : "Ada masalah") ?></h1>
            <p><?= $fails ?> masalah · <?= $warns ?> peringatan · diperiksa <?= h(date("j M Y, H:i")) ?>. Halaman ini hanya bisa dibuka admin dan tidak menampilkan isi kunci apa pun.</p>
        </div>
        <div class="adm-hero-art" aria-hidden="true"><span><?= $overall === "ok" ? "🟢" : ($overall === "warn" ? "🟡" : "🔴") ?></span></div>
    </section>

    <?php foreach ($checks as $group => $rows): ?>
    <section class="card adm-card">
        <h2><?= h($group) ?></h2>
        <ul class="status-list">
            <?php foreach ($rows as $r): ?>
                <li class="status-row is-<?= h($r["state"]) ?>">
                    <span class="status-dot" aria-hidden="true"><?= $icons[$r["state"]] ?></span>
                    <span><strong><?= h($r["label"]) ?></strong><small class="muted"><?= h($r["detail"]) ?></small></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endforeach; ?>

    <section class="grid-2">
        <div class="card adm-card">
            <h2>Tes asisten AI</h2>
            <p class="muted">Mengirim satu permintaan kecil ke layanan AI (biaya sangat kecil) untuk memastikan kunci dan modelnya benar.</p>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_ai">
                <button class="btn btn-outline" type="submit">Tes sekarang</button>
            </form>
            <?php if ($aiTest): ?>
                <div class="notice <?= $aiTest["state"] === "ok" ? "notice-info" : "notice-error" ?>" role="status"><?= h($aiTest["text"]) ?></div>
            <?php endif; ?>
        </div>

        <div class="card adm-card">
            <h2>Kunci notifikasi push</h2>
            <p class="muted">Buat sepasang kunci VAPID, lalu salin ke Environment Variables Vercel: <code>VAPID_PUBLIC_KEY</code>, <code>VAPID_PRIVATE_KEY</code>, dan <code>VAPID_SUBJECT</code> (misalnya <code>mailto:emailmu@contoh.com</code>). Kunci tidak disimpan di sini dan hanya ditampilkan sekali.</p>
            <form method="POST" data-confirm="Buat kunci baru? Kalau kunci lama sudah dipakai, pengguna harus mengaktifkan notifikasi ulang.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="gen_vapid">
                <button class="btn btn-outline" type="submit">Buat kunci baru</button>
            </form>
            <?php if ($vapid): ?>
                <div class="form-group"><label for="v-pub">VAPID_PUBLIC_KEY</label>
                    <div class="copy-row"><input class="form-control" id="v-pub" readonly value="<?= h($vapid["public"]) ?>"><button class="btn" type="button" data-copy="#v-pub">Salin</button></div></div>
                <div class="form-group"><label for="v-priv">VAPID_PRIVATE_KEY (rahasia)</label>
                    <div class="copy-row"><input class="form-control" id="v-priv" readonly value="<?= h($vapid["private"]) ?>"><button class="btn" type="button" data-copy="#v-priv">Salin</button></div></div>
                <p class="muted">Setelah menyimpan di Vercel, lakukan Redeploy agar kunci terbaca.</p>
            <?php elseif (is_post() && ($_POST["action"] ?? "") === "gen_vapid"): ?>
                <div class="notice notice-error">Kunci tidak bisa dibuat di server ini (ekstensi openssl).</div>
            <?php endif; ?>
        </div>
    </section>

</div>
<?php page_footer();
