<?php
/**
 * Pencapaian (badge). Dihitung dari data yang ada, lalu dicatat sekali saat pertama diraih.
 * Format: kode => [ikon, judul, deskripsi, metrik, ambang]
 */

const ACHIEVEMENTS = [
    "first_entry" => ["🌱", "Langkah pertama",        "Mencatat mood untuk pertama kali.",   "entries", 1],
    "entries_10"  => ["📒", "Mulai terbiasa",         "Menulis 10 catatan.",                 "entries", 10],
    "entries_30"  => ["📚", "Sebulan bercerita",      "Menulis 30 catatan.",                 "entries", 30],
    "entries_100" => ["🏆", "Seratus cerita",         "Menulis 100 catatan.",                "entries", 100],
    "streak_3"    => ["🔥", "Tiga hari beruntun",     "Mencatat 3 hari berturut-turut.",     "streak",  3],
    "streak_7"    => ["⭐", "Seminggu penuh",         "Mencatat 7 hari berturut-turut.",     "streak",  7],
    "streak_30"   => ["🌟", "Sebulan konsisten",      "Mencatat 30 hari berturut-turut.",    "streak",  30],
    "tags_5"      => ["🧭", "Penjelajah",             "Memakai 5 aktivitas yang berbeda.",   "tags",    5],
    "goal_met"    => ["🎯", "Tepat sasaran",          "Mencapai target mingguan.",           "goals",   1],
];

/** @return array{entries:int,streak:int,tags:int,goals:int} */
function achievement_metrics(int $userId): array
{
    $entries = (int) db_value("SELECT COUNT(*) FROM diary WHERE user_id = ?", "i", [$userId], 0);

    $dates = array_column(
        db_all(
            "SELECT DISTINCT diary_date FROM diary WHERE user_id = ? ORDER BY diary_date DESC LIMIT 3700",
            "i",
            [$userId]
        ),
        "diary_date"
    );
    $streak = compute_streaks($dates)["longest"];

    $used = [];
    foreach (db_all(
        "SELECT DISTINCT tags FROM diary WHERE user_id = ? AND tags IS NOT NULL AND tags <> ''",
        "i",
        [$userId]
    ) as $row) {
        foreach (tags_to_array($row["tags"]) as $t) {
            $used[$t] = true;
        }
    }

    $goalsMet = count(array_filter(goals_with_progress($userId), fn($g) => $g["met"]));

    return ["entries" => $entries, "streak" => $streak, "tags" => count($used), "goals" => $goalsMet];
}

/**
 * Periksa dan catat pencapaian baru.
 * @return string[] kode pencapaian yang baru diraih
 */
function check_achievements(int $userId): array
{
    $metrics = achievement_metrics($userId);
    $have = array_column(db_all("SELECT code FROM achievements WHERE user_id = ?", "i", [$userId]), "code");
    $have = array_flip($have);

    $new = [];
    foreach (ACHIEVEMENTS as $code => [, , , $metric, $threshold]) {
        if (isset($have[$code]) || $metrics[$metric] < $threshold) {
            continue;
        }
        [$affected] = db_exec(
            "INSERT IGNORE INTO achievements (user_id, code, earned_at) VALUES (?, ?, NOW())",
            "is",
            [$userId, $code]
        );
        if ($affected > 0) {
            $new[] = $code;
        }
    }
    return $new;
}

/** Teks singkat untuk flash message. */
function achievements_flash_text(array $codes): string
{
    $names = array_map(fn($c) => ACHIEVEMENTS[$c][0] . " " . ACHIEVEMENTS[$c][1], array_slice($codes, 0, 3));
    $text = "Pencapaian baru: " . implode(", ", $names);
    if (count($codes) > 3) {
        $text .= " dan " . (count($codes) - 3) . " lainnya";
    }
    return $text . ".";
}
