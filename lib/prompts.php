<?php
/**
 * Pertanyaan refleksi yang lembut, dikelompokkan menurut suasana hati.
 * Tidak ada yang bersifat diagnosis; semuanya hanya ajakan menulis.
 */

const PROMPTS = [
    "low" => [
        "Apa satu hal kecil yang membuatmu sedikit lebih tenang hari ini?",
        "Kalau perasaan ini bisa bicara, apa yang ingin disampaikannya?",
        "Apa yang tubuhmu butuhkan sekarang: istirahat, makan, minum, atau udara segar?",
        "Siapa yang bisa kamu hubungi kalau butuh teman bicara?",
        "Apa yang sudah berhasil kamu lewati hari ini, sekecil apa pun?",
        "Apa yang ingin kamu lepaskan dari hari ini?",
        "Kalau sahabatmu merasakan hal yang sama, apa yang akan kamu katakan padanya?",
        "Apa satu hal yang bisa kamu lakukan malam ini supaya besok sedikit lebih ringan?",
    ],
    "mid" => [
        "Apa hal paling biasa tapi menyenangkan yang terjadi hari ini?",
        "Apa yang kamu pelajari hari ini, tentang dirimu atau orang lain?",
        "Momen mana hari ini yang ingin kamu ingat?",
        "Apa satu hal kecil yang ingin kamu lakukan lebih baik besok?",
        "Apa yang menyita pikiranmu hari ini, dan apakah itu perlu dipikirkan sekarang?",
        "Kapan hari ini kamu merasa paling seperti dirimu sendiri?",
        "Apa yang membuatmu bersyukur hari ini, walaupun kecil?",
        "Apa yang ingin kamu lakukan lagi besok?",
    ],
    "high" => [
        "Apa yang membuat hari ini terasa baik, dan bagaimana kamu bisa mengulanginya?",
        "Siapa yang ingin kamu ucapkan terima kasih hari ini?",
        "Momen mana yang paling membuatmu tersenyum?",
        "Apa yang kamu banggakan dari dirimu hari ini?",
        "Apa yang ingin kamu bagikan ke orang lain dari hari yang baik ini?",
        "Apa yang kamu lakukan hari ini yang ingin kamu jadikan kebiasaan?",
        "Siapa atau apa yang paling berjasa untuk harimu?",
        "Kalau hari ini punya judul, apa judulnya?",
    ],
];

/** Indeks harian (0-365) supaya pertanyaan awal berganti tiap hari. */
function daily_prompt_seed(): int
{
    return (int) date("z");
}
