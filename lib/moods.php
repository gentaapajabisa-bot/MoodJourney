<?php
/**
 * Definisi mood, tag aktivitas, dan perhitungan statistik.
 * Skor mood 1 (berat) sampai 5 (sangat baik) dipakai untuk grafik tren.
 */

const MOODS = [
    "😄" => ["label" => "Sangat senang", "score" => 5],
    "🥰" => ["label" => "Bersyukur",     "score" => 5],
    "🙂" => ["label" => "Senang",        "score" => 4],
    "😐" => ["label" => "Biasa saja",    "score" => 3],
    "😴" => ["label" => "Lelah",         "score" => 2],
    "😰" => ["label" => "Cemas",         "score" => 2],
    "😔" => ["label" => "Sedih",         "score" => 1],
    "😡" => ["label" => "Marah",         "score" => 1],
];

const INTENSITY_LABELS = [
    1 => "Sangat ringan",
    2 => "Ringan",
    3 => "Sedang",
    4 => "Kuat",
    5 => "Sangat kuat",
];

const TAGS = [
    "tidur"    => "Tidur cukup",
    "olahraga" => "Olahraga",
    "belajar"  => "Kuliah / kerja",
    "keluarga" => "Keluarga",
    "teman"    => "Teman",
    "makan"    => "Makan sehat",
    "metime"   => "Me-time",
    "ibadah"   => "Ibadah",
    "hobi"     => "Hobi",
    "jalan"    => "Bepergian",
];

function mood_score(string $emoji): float
{
    return (float) (MOODS[$emoji]["score"] ?? 3);
}

function mood_label(string $emoji): string
{
    return MOODS[$emoji]["label"] ?? "Mood";
}

/** Skor rata-rata (1-5) dibulatkan ke mood terdekat untuk ditampilkan sebagai emoji. */
function score_to_emoji(float $score): string
{
    $rounded = (int) round($score);
    $map = [5 => "😄", 4 => "🙂", 3 => "😐", 2 => "😴", 1 => "😔"];
    return $map[max(1, min(5, $rounded))];
}

function score_label(float $score): string
{
    if ($score >= 4.5) return "Sangat baik";
    if ($score >= 3.5) return "Baik";
    if ($score >= 2.5) return "Biasa saja";
    if ($score >= 1.5) return "Agak berat";
    return "Berat";
}

function tags_to_array(?string $csv): array
{
    if ($csv === null || $csv === "") {
        return [];
    }
    $out = [];
    foreach (explode(",", $csv) as $tag) {
        $tag = trim($tag);
        if (isset(TAGS[$tag])) {
            $out[] = $tag;
        }
    }
    return $out;
}

function tags_from_input($input): string
{
    if (!is_array($input)) {
        return "";
    }
    $valid = [];
    foreach ($input as $tag) {
        if (is_string($tag) && isset(TAGS[$tag]) && !in_array($tag, $valid, true)) {
            $valid[] = $tag;
        }
    }
    return implode(",", $valid);
}

/* ---------- Streak ---------- */

/**
 * @param string[] $dates tanggal unik (Y-m-d), urutan bebas
 * @return array{current:int, longest:int, today_done:bool}
 */
function compute_streaks(array $dates): array
{
    $set = array_flip($dates);
    $today = date("Y-m-d");
    $yesterday = date("Y-m-d", strtotime("-1 day"));
    $todayDone = isset($set[$today]);

    $current = 0;
    $cursor = $todayDone ? $today : (isset($set[$yesterday]) ? $yesterday : null);
    while ($cursor !== null && isset($set[$cursor])) {
        $current++;
        $cursor = date("Y-m-d", strtotime($cursor . " -1 day"));
    }

    $sorted = array_keys($set);
    sort($sorted);
    $longest = 0;
    $run = 0;
    $prev = null;
    foreach ($sorted as $d) {
        if ($prev !== null && date("Y-m-d", strtotime($prev . " +1 day")) === $d) {
            $run++;
        } else {
            $run = 1;
        }
        $longest = max($longest, $run);
        $prev = $d;
    }

    return ["current" => $current, "longest" => $longest, "today_done" => $todayDone];
}

/* ---------- Rata-rata harian ---------- */

/**
 * Rata-rata skor per tanggal.
 * @param array $entries baris diary (minimal: mood, diary_date)
 * @return array<string,float> [Y-m-d => skor rata-rata]
 */
function daily_scores(array $entries): array
{
    $sum = [];
    $cnt = [];
    foreach ($entries as $e) {
        $d = $e["diary_date"];
        $sum[$d] = ($sum[$d] ?? 0) + mood_score($e["mood"]);
        $cnt[$d] = ($cnt[$d] ?? 0) + 1;
    }
    $out = [];
    foreach ($sum as $d => $s) {
        $out[$d] = $s / $cnt[$d];
    }
    ksort($out);
    return $out;
}

/**
 * Apakah 3 hari pencatatan terakhir (dalam 7 hari terakhir) semuanya berat?
 * Dipakai untuk menampilkan kartu dukungan yang lembut, bukan diagnosis.
 */
function needs_support_hint(int $userId): bool
{
    $since = date("Y-m-d", strtotime("-6 days"));
    $rows = db_all(
        "SELECT mood, diary_date FROM diary WHERE user_id = ? AND diary_date >= ?",
        "is",
        [$userId, $since]
    );
    $scores = daily_scores($rows);
    if (count($scores) < 3) {
        return false;
    }
    $last3 = array_slice($scores, -3, 3, true);
    foreach ($last3 as $s) {
        if ($s > 2.0) {
            return false;
        }
    }
    return true;
}
