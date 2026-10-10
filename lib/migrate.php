<?php
/**
 * Migrasi skema otomatis.
 *
 * Dijalankan sekali per versi skema: aman untuk database baru maupun database lama
 * yang hanya punya tabel users, diary, chat_rooms, messages, dan sessions.
 * Tidak memakai FOREIGN KEY supaya kompatibel dengan TiDB Cloud; penghapusan data
 * terkait dilakukan di kode aplikasi.
 */

const SCHEMA_VERSION = 3;

function column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
    );
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $count = 0;
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

function index_exists(mysqli $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
    );
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ss", $table, $index);
    $stmt->execute();
    $count = 0;
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

function add_column(mysqli $db, string $table, string $column, string $definition): void
{
    if (!column_exists($db, $table, $column)) {
        // errno 1060 = kolom sudah ada (balapan antar instance serverless), aman diabaikan
        if (!$db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition") && $db->errno !== 1060) {
            throw new RuntimeException("Migrasi gagal ($table.$column): " . $db->error);
        }
    }
}

function add_index(mysqli $db, string $table, string $index, string $columns): void
{
    if (!index_exists($db, $table, $index)) {
        // errno 1061 = indeks sudah ada
        if (!$db->query("ALTER TABLE `$table` ADD INDEX `$index` ($columns)") && $db->errno !== 1061) {
            throw new RuntimeException("Migrasi gagal (indeks $index): " . $db->error);
        }
    }
}

function run_query(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException("Migrasi gagal: " . $db->error);
    }
}

function ensure_schema(mysqli $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // Tabel penanda versi skema
    run_query($db,
        "CREATE TABLE IF NOT EXISTS app_meta (
            k VARCHAR(50) NOT NULL PRIMARY KEY,
            v VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB"
    );

    $res = $db->query("SELECT v FROM app_meta WHERE k = 'schema_version' LIMIT 1");
    $row = $res ? $res->fetch_row() : null;
    $current = $row ? (int) $row[0] : 0;

    if ($current >= SCHEMA_VERSION) {
        return;
    }

    /* ---- Tabel inti (untuk instalasi baru) ---- */
    run_query($db,
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('user','admin') NOT NULL DEFAULT 'user',
            bio TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS diary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            mood VARCHAR(30) NOT NULL,
            activity VARCHAR(150) NULL,
            note TEXT NULL,
            diary_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_diary_user (user_id)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS chat_rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_message_at DATETIME NULL,
            INDEX idx_chat_user (user_id)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_room_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_msg_room (chat_room_id),
            INDEX idx_msg_sender (sender_id)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data MEDIUMTEXT NOT NULL,
            expires_at INT UNSIGNED NOT NULL,
            INDEX idx_sessions_expires (expires_at)
        ) ENGINE=InnoDB"
    );

    /* ---- Tabel baru ---- */
    run_query($db,
        "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bucket VARCHAR(190) NOT NULL,
            created_at INT UNSIGNED NOT NULL,
            INDEX idx_rate_bucket (bucket, created_at)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NULL,
            expires_at DATETIME NULL,
            used_at DATETIME NULL,
            handled_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reset_user (user_id),
            INDEX idx_reset_token (token_hash)
        ) ENGINE=InnoDB"
    );

    run_query($db,
        "CREATE TABLE IF NOT EXISTS achievements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(40) NOT NULL,
            earned_at DATETIME NOT NULL,
            seen TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_ach_user_code (user_id, code)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tag VARCHAR(30) NOT NULL,
            target TINYINT NOT NULL DEFAULT 3,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_goals_user (user_id)
        ) ENGINE=InnoDB"
    );

    /* ---- Kolom baru di tabel lama ---- */
    add_column($db, "users", "is_active", "TINYINT(1) NOT NULL DEFAULT 1");
    add_column($db, "users", "last_login_at", "DATETIME NULL");
    add_column($db, "users", "reminder_email", "TINYINT(1) NOT NULL DEFAULT 0");
    add_column($db, "users", "last_reminded_on", "DATE NULL");

    add_column($db, "diary", "intensity", "TINYINT NOT NULL DEFAULT 3");
    add_column($db, "diary", "tags", "VARCHAR(255) NULL");
    add_column($db, "diary", "updated_at", "DATETIME NULL");
    add_index($db, "diary", "idx_diary_user_date", "user_id, diary_date");

    run_query($db,
        "REPLACE INTO app_meta (k, v) VALUES ('schema_version', '" . (int) SCHEMA_VERSION . "')"
    );
}
