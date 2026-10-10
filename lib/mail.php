<?php
/**
 * Pengiriman email opsional lewat Resend (HTTP API, tanpa library).
 * Aktif hanya jika RESEND_API_KEY, MAIL_FROM, dan APP_URL sudah diisi.
 */

function mail_enabled(): bool
{
    return env_value("RESEND_API_KEY", "") !== ""
        && env_value("MAIL_FROM", "") !== ""
        && app_base_url() !== ""
        && function_exists("curl_init");
}

function send_mail(string $to, string $subject, string $html): bool
{
    if (!mail_enabled()) {
        return false;
    }

    $ch = curl_init("https://api.resend.com/emails");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " . env_value("RESEND_API_KEY"),
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            "from"    => env_value("MAIL_FROM"),
            "to"      => [$to],
            "subject" => $subject,
            "html"    => $html,
        ]),
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log("[MoodJourney] kirim email gagal (HTTP $status)");
        return false;
    }
    return true;
}

/**
 * Kirim banyak email sekaligus (maks. 100) lewat satu panggilan API Resend.
 * @param array<int,array{to:string,subject:string,html:string}> $messages
 */
function send_mail_batch(array $messages): bool
{
    if (!mail_enabled() || !$messages) {
        return false;
    }
    $payload = [];
    foreach (array_slice($messages, 0, 100) as $m) {
        $payload[] = [
            "from"    => env_value("MAIL_FROM"),
            "to"      => [$m["to"]],
            "subject" => $m["subject"],
            "html"    => $m["html"],
        ];
    }

    $ch = curl_init("https://api.resend.com/emails/batch");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " . env_value("RESEND_API_KEY"),
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log("[MoodJourney] kirim email massal gagal (HTTP $status)");
        return false;
    }
    return true;
}
