<?php
/**
 * Wawasan pola: statistik sederhana dari catatan sendiri (tanpa AI).
 * Hanya menunjukkan kecenderungan (korelasi), bukan sebab-akibat, dan hanya kalau datanya cukup.
 */

const PATTERN_MIN_DAYS = 14;      // hari tercatat minimum sebelum pola ditampilkan
const PATTERN_MIN_TAG_DAYS = 4;   // hari minimum per aktivitas
const PATTERN_MIN_GAP = 0.3;      // selisih skor minimum agar dianggap berarti

/**
 * Hitung pola dari baris diary (mood, tags, diary_date).
 * @return array{days:int, avg:float, tags:array, weekdays:array, trend:?array, enough:bool}
 */
function patterns_compute(array $entries): array
{
    $daily = daily_scores($entries);
    $dayTags = [];
    foreach ($entries as $e) {
        foreach (tags_to_array($e["tags"] ?? "") as $t) {
            $dayTags[$e["diary_date"]][$t] = true;
        }
    }

    $days = count($daily);
    $out = [
        "days" => $days,
        "avg" => $days ? array_sum($daily) / $days : 0.0,
        "tags" => [],
        "weekdays" => [],
        "trend" => null,
        "enough" => $days >= PATTERN_MIN_DAYS,
    ];
    if (!$out["enough"]) {
        return $out;
    }

    // aktivitas: rata-rata di hari dengan vs tanpa aktivitas itu
    foreach (array_keys(TAGS) as $tag) {
        $with = [];
        $without = [];
        foreach ($daily as $d => $s) {
            if (isset($dayTags[$d][$tag])) {
                $with[] = $s;
            } else {
                $without[] = $s;
            }
        }
        if (count($with) < PATTERN_MIN_TAG_DAYS || count($without) < PATTERN_MIN_TAG_DAYS) {
            continue;
        }
        $a = array_sum($with) / count($with);
        $b = array_sum($without) / count($without);
        $out["tags"][] = ["tag" => $tag, "with" => $a, "without" => $b, "diff" => $a - $b, "n" => count($with)];
    }
    usort($out["tags"], fn($x, $y) => abs($y["diff"]) <=> abs($x["diff"]));

    // hari dalam seminggu (1 = Senin)
    $sum = array_fill(1, 7, 0.0);
    $cnt = array_fill(1, 7, 0);
    foreach ($daily as $d => $s) {
        $w = (int) date("N", strtotime($d));
        $sum[$w] += $s;
        $cnt[$w]++;
    }
    for ($w = 1; $w <= 7; $w++) {
        $out["weekdays"][$w] = $cnt[$w] >= 2 ? ["avg" => $sum[$w] / $cnt[$w], "n" => $cnt[$w]] : null;
    }

    // tren: 30 hari terakhir dibanding 30 hari sebelumnya
    $recent = [];
    $before = [];
    $t1 = strtotime("-29 days");
    $t0 = strtotime("-59 days");
    foreach ($daily as $d => $s) {
        $ts = strtotime($d);
        if ($ts >= $t1) {
            $recent[] = $s;
        } elseif ($ts >= $t0) {
            $before[] = $s;
        }
    }
    if (count($recent) >= 5 && count($before) >= 5) {
        $out["trend"] = ["recent" => array_sum($recent) / count($recent), "before" => array_sum($before) / count($before)];
    }
    return $out;
}

/** Kalimat wawasan yang bisa dibaca manusia, paling kuat lebih dulu. */
function patterns_sentences(array $p): array
{
    $names = ["", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"];
    $items = [];

    foreach ($p["tags"] as $t) {
        if (abs($t["diff"]) < PATTERN_MIN_GAP) {
            continue;
        }
        $label = TAGS[$t["tag"]] ?? $t["tag"];
        $up = $t["diff"] > 0;
        $items[] = [
            "icon" => $up ? "🌿" : "🌧️",
            "tone" => $up ? "up" : "down",
            "text" => $up
                ? "Di hari ada \"" . $label . "\", moodmu cenderung lebih baik (" . number_format($t["with"], 1) . " dibanding " . number_format($t["without"], 1) . ")."
                : "Di hari ada \"" . $label . "\", moodmu cenderung lebih berat (" . number_format($t["with"], 1) . " dibanding " . number_format($t["without"], 1) . "). Bisa jadi itu hari yang memang melelahkan, bukan aktivitasnya.",
            "note" => $t["n"] . " hari tercatat",
            "weight" => abs($t["diff"]),
        ];
    }

    $best = null;
    $worst = null;
    foreach ($p["weekdays"] as $w => $v) {
        if ($v === null) {
            continue;
        }
        if ($best === null || $v["avg"] > $p["weekdays"][$best]["avg"]) {
            $best = $w;
        }
        if ($worst === null || $v["avg"] < $p["weekdays"][$worst]["avg"]) {
            $worst = $w;
        }
    }
    if ($best !== null && $worst !== null && $best !== $worst && $p["weekdays"][$best]["avg"] - $p["weekdays"][$worst]["avg"] >= PATTERN_MIN_GAP) {
        $items[] = ["icon" => "📅", "tone" => "up", "weight" => 0.5,
            "text" => "Harimu paling cerah biasanya " . $names[$best] . ", dan paling berat " . $names[$worst] . ".",
            "note" => "dari rata-rata skor per hari"];
    }

    if ($p["trend"] !== null) {
        $d = $p["trend"]["recent"] - $p["trend"]["before"];
        if (abs($d) >= 0.25) {
            $items[] = ["icon" => $d > 0 ? "📈" : "📉", "tone" => $d > 0 ? "up" : "down", "weight" => 0.6,
                "text" => $d > 0
                    ? "30 hari terakhir terasa lebih baik daripada 30 hari sebelumnya."
                    : "30 hari terakhir terasa lebih berat daripada 30 hari sebelumnya. Pelan-pelan saja, dan kamu tidak sendirian.",
                "note" => number_format($p["trend"]["before"], 1) . " → " . number_format($p["trend"]["recent"], 1)];
        } else {
            $items[] = ["icon" => "〰️", "tone" => "flat", "weight" => 0.1,
                "text" => "Moodmu cukup stabil dibanding bulan sebelumnya.", "note" => "selisih kecil"];
        }
    }

    usort($items, fn($a, $b) => $b["weight"] <=> $a["weight"]);
    return $items;
}
