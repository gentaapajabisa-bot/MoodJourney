<?php
/**
 * Grafik dirender di server sebagai SVG/HTML: tanpa library JS, cepat, dan ikut tema gelap.
 */

/** Singkatan tanggal pendek: "5 Okt". */
function short_date(string $date): string
{
    $ts = strtotime($date);
    return (int) date("j", $ts) . " " . mb_substr(MONTHS_ID[(int) date("n", $ts)], 0, 3);
}

/**
 * Grafik garis skor mood harian.
 * @param array<string,float> $scores [Y-m-d => skor]
 */
function svg_trend(array $scores, string $from, string $to): string
{
    $W = 680; $H = 240;
    $padL = 44; $padR = 16; $padT = 16; $padB = 34;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;

    $start = strtotime($from);
    $end = strtotime($to);
    $days = max(1, (int) round(($end - $start) / 86400));

    $x = fn(string $d) => $padL + ((strtotime($d) - $start) / 86400) / $days * $plotW;
    $y = fn(float $s) => $padT + (5 - $s) / 4 * $plotH;

    $faces = [5 => "😄", 4 => "🙂", 3 => "😐", 2 => "😴", 1 => "😔"];

    $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Grafik tren mood harian">';

    foreach ($faces as $level => $face) {
        $yy = round($y((float) $level), 1);
        $svg .= '<line class="chart-grid" x1="' . $padL . '" x2="' . ($W - $padR) . '" y1="' . $yy . '" y2="' . $yy . '"/>';
        $svg .= '<text class="chart-face" x="' . ($padL - 10) . '" y="' . ($yy + 5) . '" text-anchor="end">' . $face . '</text>';
    }

    // label sumbu X: awal, tengah, akhir
    $mid = date("Y-m-d", (int) (($start + $end) / 2));
    foreach ([[$from, "start"], [$mid, "middle"], [$to, "end"]] as [$d, $anchor]) {
        $xx = round($x($d), 1);
        $svg .= '<text class="chart-label" x="' . $xx . '" y="' . ($H - 10) . '" text-anchor="' . $anchor . '">' . h(short_date($d)) . '</text>';
    }

    $points = [];
    foreach ($scores as $d => $s) {
        if ($d < $from || $d > $to) {
            continue;
        }
        $points[] = [round($x($d), 1), round($y((float) $s), 1), $d, $s];
    }

    if (count($points) >= 2) {
        $path = "";
        foreach ($points as $i => [$px, $py]) {
            $path .= ($i === 0 ? "M" : "L") . $px . " " . $py . " ";
        }
        $svg .= '<path class="chart-line" d="' . trim($path) . '"/>';
    }
    foreach ($points as [$px, $py, $d, $s]) {
        $svg .= '<circle class="chart-dot" cx="' . $px . '" cy="' . $py . '" r="4.5"><title>'
              . h(date_id($d) . ": " . score_label((float) $s) . " (" . number_format((float) $s, 1) . ")") . '</title></circle>';
    }

    $svg .= '</svg>';
    return $svg;
}

/**
 * Grafik kolom sederhana dari HTML.
 * @param array<int,array{label:string,value:float|int,title?:string}> $items
 */
function html_columns(array $items, float $max = 0): string
{
    if ($max <= 0) {
        $max = 1;
        foreach ($items as $it) {
            $max = max($max, (float) $it["value"]);
        }
    }
    $html = '<div class="columns" role="img" aria-label="Grafik kolom">';
    foreach ($items as $it) {
        $pct = $max > 0 ? round(((float) $it["value"]) / $max * 100) : 0;
        $title = $it["title"] ?? ($it["label"] . ": " . $it["value"]);
        $html .= '<div class="column" title="' . h($title) . '">'
              . '<span class="column-value">' . h($it["display"] ?? $it["value"]) . '</span>'
              . '<div class="column-track"><div class="column-fill" style="height:' . $pct . '%"></div></div>'
              . '<span class="column-label">' . h($it["label"]) . '</span>'
              . '</div>';
    }
    return $html . '</div>';
}

/**
 * Kalender bulanan. Tiap hari berwarna sesuai rata-rata skor dan menampilkan emoji.
 * @param array<string,array{emoji:string,score:float}> $days
 */
function html_calendar(string $ym, array $days): string
{
    $first = strtotime($ym . "-01");
    $daysInMonth = (int) date("t", $first);
    $offset = (int) date("N", $first) - 1; // Senin = 0
    $today = date("Y-m-d");

    $html = '<div class="calendar" role="grid" aria-label="Kalender mood ' . h(month_id($ym)) . '">';
    foreach (["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"] as $dn) {
        $html .= '<div class="cal-head" role="columnheader">' . $dn . '</div>';
    }
    for ($i = 0; $i < $offset; $i++) {
        $html .= '<div class="cal-cell cal-empty" aria-hidden="true"></div>';
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = sprintf("%s-%02d", $ym, $d);
        $info = $days[$date] ?? null;
        $classes = "cal-cell";
        if ($date === $today) {
            $classes .= " cal-today";
        }
        if ($date > $today) {
            $classes .= " cal-future";
        }
        if ($info) {
            $score = (int) round($info["score"]);
            $classes .= " cal-filled";
            $html .= '<a class="' . $classes . '" data-score="' . $score . '" href="/history.php?from=' . $date . '&to=' . $date . '"'
                  . ' title="' . h(date_id($date) . ": " . score_label($info["score"])) . '" role="gridcell">'
                  . '<span class="cal-day">' . $d . '</span><span class="cal-emoji">' . h($info["emoji"]) . '</span></a>';
        } else {
            $html .= '<div class="' . $classes . '" role="gridcell"><span class="cal-day">' . $d . '</span></div>';
        }
    }
    return $html . '</div>';
}
