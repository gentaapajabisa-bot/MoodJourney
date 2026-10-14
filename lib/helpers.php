<?php
/**
 * Fungsi bantu yang dipakai semua halaman.
 */

/* ---------- Output ---------- */

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function redirect(string $path): void
{
    header("Location: " . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST";
}

/* ---------- Flash message ---------- */

function flash(string $type, string $message): void
{
    $_SESSION["flash"][] = ["type" => $type, "message" => $message];
}

function flash_take(): array
{
    $items = $_SESSION["flash"] ?? [];
    unset($_SESSION["flash"]);
    return $items;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/**
 * Wajib dipanggil di awal setiap handler POST.
 * Kalau token tidak cocok, pengguna dikembalikan ke halaman sebelumnya.
 */
function csrf_verify(string $backTo = "/"): void
{
    $sent = $_POST["_csrf"] ?? "";
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        flash("error", "Sesi formulir sudah kedaluwarsa. Silakan coba lagi.");
        redirect($backTo);
    }
}

/* ---------- Database ---------- */

/**
 * prepare() yang melempar exception dengan pesan jelas, bukan mengembalikan false.
 */
function db_prepare(string $sql): mysqli_stmt
{
    $db = db_connect();
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        error_log("[MoodJourney] prepare gagal: " . $db->error . " | SQL: " . $sql);
        throw new RuntimeException("Query gagal disiapkan: " . $db->error);
    }
    return $stmt;
}

/**
 * Jalankan query dan kembalikan statement (sudah di-execute).
 * $types contoh: "is" (integer, string). Lihat mysqli_stmt::bind_param.
 */
function db_run(string $sql, string $types = "", array $params = []): mysqli_stmt
{
    $stmt = db_prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $err = $stmt->error;
        error_log("[MoodJourney] execute gagal: " . $err . " | SQL: " . $sql);
        throw new RuntimeException("Query gagal dijalankan: " . $err);
    }
    return $stmt;
}

function db_all(string $sql, string $types = "", array $params = []): array
{
    $stmt = db_run($sql, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_one(string $sql, string $types = "", array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function db_value(string $sql, string $types = "", array $params = [], $default = null)
{
    $row = db_one($sql, $types, $params);
    if ($row === null) {
        return $default;
    }
    return array_values($row)[0] ?? $default;
}

/** Untuk INSERT/UPDATE/DELETE. Mengembalikan [affected_rows, insert_id]. */
function db_exec(string $sql, string $types = "", array $params = []): array
{
    $stmt = db_run($sql, $types, $params);
    $out = [$stmt->affected_rows, $stmt->insert_id];
    $stmt->close();
    return $out;
}

/** Escape karakter khusus untuk LIKE. */
function like_escape(string $value): string
{
    return addcslashes($value, "\\%_");
}

/* ---------- Tanggal (bahasa Indonesia) ---------- */

const MONTHS_ID = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember",
];

const DAYS_ID = [
    1 => "Senin", 2 => "Selasa", 3 => "Rabu", 4 => "Kamis",
    5 => "Jumat", 6 => "Sabtu", 7 => "Minggu",
];

function date_id(string $date, bool $withDay = false): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $text = (int) date("j", $ts) . " " . MONTHS_ID[(int) date("n", $ts)] . " " . date("Y", $ts);
    return $withDay ? DAYS_ID[(int) date("N", $ts)] . ", " . $text : $text;
}

function month_id(string $ym): string
{
    $ts = strtotime($ym . "-01");
    return MONTHS_ID[(int) date("n", $ts)] . " " . date("Y", $ts);
}

function valid_date(string $value): bool
{
    $d = DateTime::createFromFormat("Y-m-d", $value);
    return $d && $d->format("Y-m-d") === $value;
}

/* ---------- Jaringan ---------- */

function client_ip(): string
{
    $forwarded = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? "";
    if ($forwarded !== "") {
        $first = trim(explode(",", $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
}

/* ---------- Rate limit sederhana (tabel rate_limits) ---------- */

function rate_hit(string $bucket): void
{
    db_exec(
        "INSERT INTO rate_limits (bucket, created_at) VALUES (?, ?)",
        "si",
        [substr($bucket, 0, 190), time()]
    );
}

function rate_count(string $bucket, int $windowSeconds): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at > ?",
        "si",
        [substr($bucket, 0, 190), time() - $windowSeconds],
        0
    );
}

function rate_clear(string $bucket): void
{
    db_exec("DELETE FROM rate_limits WHERE bucket = ?", "s", [substr($bucket, 0, 190)]);
}

/* ---------- Lain-lain ---------- */

function app_base_url(): string
{
    $url = (string) env_value("APP_URL", "");
    return rtrim($url, "/");
}

function str_limit(string $text, int $max): string
{
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . "…";
}

/**
 * Hapus akun beserta semua data terkait. Tidak ada FOREIGN KEY di skema,
 * jadi pembersihan dilakukan di sini.
 */
function delete_user_data(int $userId): void
{
    $db = db_connect();
    $db->begin_transaction();
    try {
        // pesan di ruang chat milik pengguna + pesan yang ia kirim di ruang mana pun
        db_exec("DELETE FROM messages WHERE chat_room_id IN (SELECT id FROM chat_rooms WHERE user_id = ?)", "i", [$userId]);
        db_exec("DELETE FROM messages WHERE sender_id = ?", "i", [$userId]);
        db_exec("DELETE FROM chat_rooms WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM diary WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM password_resets WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM achievements WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM diary_photos WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM user_avatars WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM support_requests WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM goals WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM push_subscriptions WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM letters WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM butterfly_collection WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM challenge_done WHERE user_id = ?", "i", [$userId]);
        db_exec("DELETE FROM users WHERE id = ?", "i", [$userId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Tanggal Senin dari minggu yang memuat $date (format Y-m-d). */
function week_start(string $date): string
{
    $ts = strtotime($date);
    $n = (int) date("N", $ts);
    return date("Y-m-d", strtotime("-" . ($n - 1) . " days", $ts));
}

function week_end(string $weekStart): string
{
    return date("Y-m-d", strtotime($weekStart . " +6 days"));
}

/** Contoh: "6 Okt – 12 Okt 2026" */
function week_label(string $weekStart): string
{
    $end = week_end($weekStart);
    return short_date($weekStart) . " – " . short_date($end) . " " . date("Y", strtotime($end));
}

/** Admin dan konselor dianggap staf. */
function is_staff(string $role): bool
{
    return $role === "admin" || $role === "counselor";
}

/** Halaman awal sesuai peran. */
function home_for(array $user): string
{
    if ($user["role"] === "admin") {
        return "/admin/";
    }
    if ($user["role"] === "counselor") {
        return "/konselor/";
    }
    return "/dashboard.php";
}
