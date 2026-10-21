<?php
/**
 * Migrasi skema otomatis.
 *
 * Dijalankan sekali per versi skema: aman untuk database baru maupun database lama
 * yang hanya punya tabel users, diary, chat_rooms, messages, dan sessions.
 * Tidak memakai FOREIGN KEY supaya kompatibel dengan TiDB Cloud; penghapusan data
 * terkait dilakukan di kode aplikasi.
 */

const SCHEMA_VERSION = 7;

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

function column_type(mysqli $db, string $table, string $column): string
{
    $stmt = $db->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
    );
    if ($stmt === false) {
        return "";
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $type = "";
    $stmt->bind_result($type);
    $stmt->fetch();
    $stmt->close();
    return (string) $type;
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

    run_query($db,
        "CREATE TABLE IF NOT EXISTS diary_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            diary_id INT NOT NULL,
            user_id INT NOT NULL,
            mime VARCHAR(30) NOT NULL,
            size INT NOT NULL,
            data MEDIUMBLOB NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_photo_diary (diary_id),
            INDEX idx_photo_user (user_id)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS user_avatars (
            user_id INT NOT NULL PRIMARY KEY,
            mime VARCHAR(30) NOT NULL,
            size INT NOT NULL,
            data MEDIUMBLOB NOT NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS support_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            topic VARCHAR(60) NOT NULL,
            message TEXT NULL,
            status ENUM('waiting','active','done','cancelled') NOT NULL DEFAULT 'waiting',
            counselor_id INT NULL,
            urgent TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_sr_user (user_id),
            INDEX idx_sr_status (status),
            INDEX idx_sr_counselor (counselor_id)
        ) ENGINE=InnoDB"
    );

    /* ---- Kolom baru di tabel lama ---- */
    add_column($db, "users", "is_active", "TINYINT(1) NOT NULL DEFAULT 1");
    add_column($db, "users", "last_login_at", "DATETIME NULL");
    add_column($db, "users", "reminder_email", "TINYINT(1) NOT NULL DEFAULT 0");
    add_column($db, "users", "last_reminded_on", "DATE NULL");
    add_column($db, "users", "avatar_v", "INT NULL");


    // skema v6: notifikasi push, kunci PIN, surat untuk diri sendiri, koleksi kupu-kupu, tantangan mingguan
    add_column($db, "users", "pin_hash", "VARCHAR(255) NULL");
    add_column($db, "users", "last_pushed_on", "DATE NULL");
    run_query($db,
        "CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(160) NOT NULL,
            auth VARCHAR(60) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_push_endpoint (endpoint_hash),
            INDEX idx_push_user (user_id)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS letters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            body TEXT NOT NULL,
            open_on DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            opened_at DATETIME NULL,
            INDEX idx_letters_user (user_id, open_on)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS butterfly_collection (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            stage VARCHAR(10) NOT NULL,
            mood VARCHAR(30) NULL,
            c1 CHAR(7) NOT NULL,
            c2 CHAR(7) NOT NULL,
            earned_on DATE NOT NULL,
            UNIQUE KEY uq_collection (user_id, stage, earned_on),
            INDEX idx_collection_user (user_id)
        ) ENGINE=InnoDB"
    );
    run_query($db,
        "CREATE TABLE IF NOT EXISTS challenge_done (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            week_start DATE NOT NULL,
            code VARCHAR(30) NOT NULL,
            done_on DATE NOT NULL,
            UNIQUE KEY uq_challenge (user_id, week_start)
        ) ENGINE=InnoDB"
    );

    // skema v5: satu catatan boleh punya banyak foto (hapus indeks UNIQUE lama, ganti indeks biasa)
    if (index_exists($db, "diary_photos", "uq_photo_diary")) {
        // errno 1091 = indeks sudah terhapus (permintaan lain lebih dulu)
        if (!$db->query("ALTER TABLE diary_photos DROP INDEX uq_photo_diary") && $db->errno !== 1091) {
            throw new RuntimeException("Migrasi gagal (hapus indeks uq_photo_diary): " . $db->error);
        }
    }
    add_index($db, "diary_photos", "idx_photo_diary", "diary_id");

    // peran konselor: tambahkan ke ENUM role bila belum ada
    if (strpos(column_type($db, "users", "role"), "counselor") === false) {
        run_query($db, "ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','counselor') NOT NULL DEFAULT 'user'");
    }
    add_column($db, "messages", "flagged", "TINYINT(1) NOT NULL DEFAULT 0");

    add_column($db, "diary", "intensity", "TINYINT NOT NULL DEFAULT 3");
    add_column($db, "diary", "tags", "VARCHAR(255) NULL");
    add_column($db, "diary", "updated_at", "DATETIME NULL");
    add_index($db, "diary", "idx_diary_user_date", "user_id, diary_date");

    // skema v7: formulir konselor (isian form + ketersediaan konselor)
    add_column($db, "users", "counselor_available", "TINYINT(1) NOT NULL DEFAULT 1");
    add_column($db, "users", "counselor_topics", "VARCHAR(255) NULL");
    add_column($db, "users", "counselor_hours", "VARCHAR(120) NULL");
    add_column($db, "support_requests", "feeling_level", "TINYINT NULL");
    add_column($db, "support_requests", "duration", "VARCHAR(20) NULL");
    add_column($db, "support_requests", "preferred_time", "VARCHAR(20) NULL");
    add_column($db, "support_requests", "first_time", "TINYINT(1) NULL");
    add_column($db, "support_requests", "preferred_counselor_id", "INT NULL");
    add_column($db, "support_requests", "form_data", "MEDIUMTEXT NULL");
    add_column($db, "support_requests", "phone", "VARCHAR(25) NULL");

    run_query($db,
        "REPLACE INTO app_meta (k, v) VALUES ('schema_version', '" . (int) SCHEMA_VERSION . "')"
    );
}
