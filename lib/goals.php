<?php
/**
 * Target kebiasaan mingguan: "lakukan aktivitas X di N hari dalam seminggu".
 * Satu hari dihitung sekali walaupun ada beberapa catatan dengan tag yang sama.
 */

const MAX_GOALS = 5;

function goal_like(string $tag): string
{
    return "%," . $tag . ",%";
}

function goal_count_between(int $userId, string $tag, string $from, string $to): int
{
    return (int) db_value(
        "SELECT COUNT(DISTINCT diary_date) FROM diary
         WHERE user_id = ? AND diary_date BETWEEN ? AND ?
           AND CONCAT(',', COALESCE(tags, ''), ',') LIKE ?",
        "isss",
        [$userId, $from, $to, goal_like($tag)],
        0
    );
}

/** Target pengguna beserta progres minggu berjalan. */
function goals_with_progress(int $userId, ?string $weekStart = null): array
{
    $ws = $weekStart ?? week_start(date("Y-m-d"));
    $we = week_end($ws);

    $goals = db_all("SELECT id, tag, target FROM goals WHERE user_id = ? ORDER BY id ASC", "i", [$userId]);
    foreach ($goals as &$g) {
        $g["target"] = (int) $g["target"];
        $g["done"] = goal_count_between($userId, $g["tag"], $ws, $we);
        $g["met"] = $g["done"] >= $g["target"];
        $g["label"] = TAGS[$g["tag"]] ?? $g["tag"];
    }
    unset($g);
    return $goals;
}

/**
 * Jumlah hari per minggu untuk beberapa minggu penuh sebelum minggu ini (lama -> baru).
 * @return array<int,array{start:string,count:int}>
 */
function goal_history(int $userId, string $tag, int $weeks = 4): array
{
    $thisWeek = week_start(date("Y-m-d"));
    $from = date("Y-m-d", strtotime($thisWeek . " -{$weeks} weeks"));
    $to = date("Y-m-d", strtotime($thisWeek . " -1 day"));

    $rows = db_all(
        "SELECT DISTINCT diary_date FROM diary
         WHERE user_id = ? AND diary_date BETWEEN ? AND ?
           AND CONCAT(',', COALESCE(tags, ''), ',') LIKE ?",
        "isss",
        [$userId, $from, $to, goal_like($tag)]
    );

    $counts = [];
    foreach ($rows as $r) {
        $ws = week_start($r["diary_date"]);
        $counts[$ws] = ($counts[$ws] ?? 0) + 1;
    }

    $out = [];
    for ($i = $weeks; $i >= 1; $i--) {
        $ws = date("Y-m-d", strtotime($thisWeek . " -{$i} weeks"));
        $out[] = ["start" => $ws, "count" => $counts[$ws] ?? 0];
    }
    return $out;
}
