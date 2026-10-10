<?php
/**
 * Koneksi database MySQL/MariaDB/TiDB.
 * Semua pengaturan dibaca dari Environment Variables (Vercel > Settings > Environment Variables).
 * Default-nya masih cocok dengan XAMPP lokal.
 */
if (!function_exists("db_connect")) {
    function env_value(string $key, $default = "")
    {
        $value = getenv($key);
        if ($value === false || $value === "") {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? "";
        }
        return $value === "" ? $default : $value;
    }

    function db_connect(): mysqli
    {
        static $conn = null;
        if ($conn instanceof mysqli) {
            return $conn;
        }

        $host     = env_value("DB_HOST", "localhost");
        $port     = (int) env_value("DB_PORT", 3306);
        $user     = env_value("DB_USER", "root");
        $password = env_value("DB_PASSWORD", "");
        $database = env_value("DB_NAME", "moodjourney");
        $local    = in_array($host, ["localhost", "127.0.0.1"], true);
        $useSsl   = env_value("DB_SSL", $local ? "0" : "1") === "1";

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_init();
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

        $flags = 0;
        if ($useSsl) {
            $ca = env_value("DB_SSL_CA", "");
            $conn->ssl_set(null, null, $ca !== "" ? $ca : null, null, null);
            $flags = MYSQLI_CLIENT_SSL;
        }

        if (!@$conn->real_connect($host, $user, $password, $database, $port, null, $flags)) {
            http_response_code(500);
            die("Koneksi database gagal. Periksa Environment Variables DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME.");
        }

        $conn->set_charset("utf8mb4");

        // Samakan zona waktu sesi database dengan zona waktu aplikasi (default WIB).
        $offset = date("P");
        $conn->query("SET time_zone = '" . $conn->real_escape_string($offset) . "'");

        return $conn;
    }
}

$conn = db_connect();
