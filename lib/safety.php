<?php
/**
 * Deteksi tanda krisis berbasis aturan tetap (bukan AI), supaya selalu berjalan.
 * Sengaja dibuat peka: lebih baik menampilkan bantuan yang ternyata tidak perlu
 * daripada melewatkan seseorang yang membutuhkannya.
 */

function safety_normalize(string $text): string
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', " ", $text) ?? $text;
    return trim(preg_replace('/\s+/u', " ", $text) ?? $text);
}

function crisis_detect(string $text): bool
{
    $t = " " . safety_normalize($text) . " ";
    if (trim($t) === "") {
        return false;
    }

    $phrases = [
        "bunuh diri", "membunuh diri", "bunuh aku sendiri", "gantung diri", "lompat dari",
        "mau mati", "ingin mati", "pengen mati", "pingin mati", "pengin mati", "mau meninggal",
        "mengakhiri hidup", "akhiri hidup", "akhirin hidup", "ngakhirin hidup", "mengakhiri semuanya",
        "tidak ingin hidup", "tidak mau hidup", "gak mau hidup", "nggak mau hidup", "ga mau hidup",
        "gak ingin hidup", "nggak ingin hidup", "tidak ingin hidup lagi",
        "lebih baik mati", "lebih baik aku mati", "lebih baik tidak ada", "lebih baik gak ada",
        "tidak ada gunanya hidup", "gak ada gunanya hidup", "capek hidup", "lelah hidup", "cape hidup",
        "menyakiti diri", "melukai diri", "nyakitin diri", "melukai diriku", "menyayat", "nyayat",
        "self harm", "selfharm", "overdosis", "overdose",
        "suicide", "suicidal", "kill myself", "end my life", "want to die", "wanna die",
    ];

    foreach ($phrases as $p) {
        if (strpos($t, " " . $p . " ") !== false || strpos($t, $p) !== false) {
            return true;
        }
    }
    return false;
}

/** Pesan lembut yang selalu memuat jalur bantuan nyata. */
function crisis_message(): string
{
    return "Terima kasih sudah mau menuliskannya. Kalau kamu dalam bahaya sekarang atau berpikir untuk menyakiti dirimu, "
         . "tolong hubungi 112 atau datang ke IGD terdekat. Kamu juga bisa menghubungi Healing119.id (Kementerian Kesehatan): "
         . "telepon 119 lalu tekan 8. Kalau memungkinkan, kabari orang yang kamu percaya supaya kamu tidak sendirian.";
}
