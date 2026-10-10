<?php
/**
 * Sesi login yang disimpan di tabel `sessions`.
 * Perlu karena fungsi serverless Vercel tidak punya penyimpanan file yang menetap,
 * sehingga session bawaan PHP (file) akan hilang di setiap permintaan.
 */
require_once __DIR__ . "/database.php";

if (!class_exists("DbSessionHandler")) {
    class DbSessionHandler implements SessionHandlerInterface
    {
        private mysqli $db;
        private int $ttl;

        public function __construct(mysqli $db, int $ttl)
        {
            $this->db = $db;
            $this->ttl = $ttl;
        }

        private function stmt(string $sql): mysqli_stmt
        {
            $stmt = $this->db->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException("Tabel sessions bermasalah: " . $this->db->error);
            }
            return $stmt;
        }

        public function open(string $path, string $name): bool { return true; }
        public function close(): bool { return true; }

        public function read(string $id): string|false
        {
            $stmt = $this->stmt("SELECT data FROM sessions WHERE id = ? AND expires_at > ? LIMIT 1");
            $now = time();
            $stmt->bind_param("si", $id, $now);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ? (string) $row["data"] : "";
        }

        public function write(string $id, string $data): bool
        {
            $expires = time() + $this->ttl;
            $stmt = $this->stmt(
                "INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)"
            );
            $stmt->bind_param("ssi", $id, $data, $expires);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }

        public function destroy(string $id): bool
        {
            $stmt = $this->stmt("DELETE FROM sessions WHERE id = ?");
            $stmt->bind_param("s", $id);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }

        public function gc(int $max_lifetime): int|false
        {
            $now = time();
            $stmt = $this->stmt("DELETE FROM sessions WHERE expires_at < ?");
            $stmt->bind_param("i", $now);
            $stmt->execute();
            $n = $stmt->affected_rows;
            $stmt->close();
            return $n;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $ttl = 60 * 60 * 24 * 7; // 7 hari
    $https = (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https") || !empty($_SERVER["HTTPS"]);

    session_set_save_handler(new DbSessionHandler(db_connect(), $ttl), true);
    session_set_cookie_params([
        "lifetime" => $ttl,
        "path"     => "/",
        "secure"   => $https,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();

    // Bersihkan sesi dan catatan rate limit kedaluwarsa sesekali (1% permintaan)
    if (random_int(1, 100) === 1) {
        $now = time();
        db_connect()->query("DELETE FROM sessions WHERE expires_at < " . $now);
        db_connect()->query("DELETE FROM rate_limits WHERE created_at < " . ($now - 86400));
    }
}
