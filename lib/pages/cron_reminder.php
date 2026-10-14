<?php
/**
 * Dipanggil sekali sehari oleh Vercel Cron (lihat vercel.json).
 * Mengirim email pengingat ke pengguna yang mengaktifkannya dan belum mencatat hari ini.
 * Dilindungi CRON_SECRET: Vercel mengirimnya sebagai "Authorization: Bearer <CRON_SECRET>".
 */
header("Content-Type: text/plain; charset=UTF-8");

$secret = (string) env_value("CRON_SECRET", "");
$auth = (string) ($_SERVER["HTTP_AUTHORIZATION"] ?? "");

if ($secret === "" || !hash_equals("Bearer " . $secret, $auth)) {
    http_response_code(401);
    echo "unauthorized";
    exit;
}

$today = date("Y-m-d");
try {
    db_exec("REPLACE INTO app_meta (k, v) VALUES ('cron_last', ?)", "s", [date("Y-m-d H:i")]);
} catch (Throwable $e) {
    error_log("[MoodJourney] cron_last: " . $e->getMessage());
}

/* 1. Notifikasi push (perangkat yang sudah diaktifkan pengguna) */
$pushSent = 0;
if (push_enabled()) {
    $texts = [
        ["Kupu-kupumu menunggu 🦋", "Cukup satu emoji untuk menjaga perjalananmu hari ini."],
        ["Bagaimana harimu?", "Satu menit untuk mencatat mood, ya."],
        ["Sudah sempat mencatat?", "Hari ini belum ada catatan. Tidak perlu panjang."],
        ["Hai, ada waktu sebentar?", "Ceritakan perasaanmu hari ini di MoodJourney."],
    ];
    $pick = $texts[(int) date("z") % count($texts)];
    $pushUsers = db_all(
        "SELECT u.id FROM users u
         WHERE u.role = 'user' AND u.is_active = 1
           AND (u.last_pushed_on IS NULL OR u.last_pushed_on < ?)
           AND EXISTS (SELECT 1 FROM push_subscriptions p WHERE p.user_id = u.id)
           AND NOT EXISTS (SELECT 1 FROM diary d WHERE d.user_id = u.id AND d.diary_date = ?)
         LIMIT 200",
        "ss",
        [$today, $today]
    );
    foreach ($pushUsers as $pu) {
        if (push_send_user((int) $pu["id"], ["title" => $pick[0], "body" => $pick[1], "url" => "/cepat.php"]) > 0) {
            $pushSent++;
        }
        db_exec("UPDATE users SET last_pushed_on = ? WHERE id = ?", "si", [$today, (int) $pu["id"]]);
    }
}
/* Surat untuk diri sendiri yang terbuka hari ini */
if (push_enabled()) {
    foreach (db_all("SELECT DISTINCT user_id FROM letters WHERE open_on = ? AND opened_at IS NULL LIMIT 200", "s", [$today]) as $lr) {
        push_send_user((int) $lr["user_id"], ["title" => "Ada surat dari dirimu 💌", "body" => "Suratmu hari ini sudah boleh dibuka.", "url" => "/surat.php"]);
    }
}
echo "push: " . $pushSent . "\n";

/* 2. Email */
if (!mail_enabled()) {
    echo "email belum dikonfigurasi";
    exit;
}

$users = db_all(
    "SELECT u.id, u.name, u.email FROM users u
     WHERE u.role = 'user' AND u.is_active = 1 AND u.reminder_email = 1
       AND (u.last_reminded_on IS NULL OR u.last_reminded_on < ?)
       AND NOT EXISTS (SELECT 1 FROM diary d WHERE d.user_id = u.id AND d.diary_date = ?)
     LIMIT 100",
    "ss",
    [$today, $today]
);

if (!$users) {
    echo "tidak ada yang perlu diingatkan";
    exit;
}

$link = app_base_url() . "/diary.php";
$messages = [];
foreach ($users as $u) {
    $messages[] = [
        "to"      => $u["email"],
        "subject" => "Sudah sempat mencatat mood hari ini?",
        "html"    => "<p>Halo " . h($u["name"]) . ",</p>"
                   . "<p>Hari ini belum ada catatan di MoodJourney. Cukup satu emoji dan satu kalimat sudah cukup.</p>"
                   . '<p><a href="' . h($link) . '">Catat mood sekarang</a></p>'
                   . "<p style=\"color:#777;font-size:13px\">Kamu bisa mematikan pengingat ini kapan saja di halaman Profil.</p>",
    ];
}

if (send_mail_batch($messages)) {
    foreach ($users as $u) {
        db_exec("UPDATE users SET last_reminded_on = ? WHERE id = ?", "si", [$today, (int) $u["id"]]);
    }
    echo "terkirim: " . count($users);
} else {
    http_response_code(502);
    echo "gagal mengirim";
}
