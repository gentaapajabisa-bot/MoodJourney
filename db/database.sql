-- MoodJourney: skema lengkap (instalasi baru).
-- Catatan: aplikasi menjalankan migrasi ini otomatis saat pertama kali dibuka,
-- jadi file ini hanya perlu dijalankan manual kalau kamu mau menyiapkan database lebih dulu.
-- Tidak memakai FOREIGN KEY agar kompatibel dengan TiDB Cloud.

CREATE TABLE IF NOT EXISTS app_meta (
    k VARCHAR(50) NOT NULL PRIMARY KEY,
    v VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin','counselor') NOT NULL DEFAULT 'user',
    counselor_available TINYINT(1) NOT NULL DEFAULT 1,
    counselor_topics VARCHAR(255) NULL,
    counselor_hours VARCHAR(120) NULL,
    bio TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    reminder_email TINYINT(1) NOT NULL DEFAULT 0,
    last_reminded_on DATE NULL,
    avatar_v INT NULL,
    pin_hash VARCHAR(255) NULL,
    last_pushed_on DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mood VARCHAR(30) NOT NULL,
    intensity TINYINT NOT NULL DEFAULT 3,
    tags VARCHAR(255) NULL,
    activity VARCHAR(150) NULL,
    note TEXT NULL,
    diary_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_diary_user (user_id),
    INDEX idx_diary_user_date (user_id, diary_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at DATETIME NULL,
    INDEX idx_chat_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_room_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    flagged TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_room (chat_room_id),
    INDEX idx_msg_sender (sender_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data MEDIUMTEXT NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    INDEX idx_sessions_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(190) NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX idx_rate_bucket (bucket, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NULL,
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    handled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_user (user_id),
    INDEX idx_reset_token (token_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(40) NOT NULL,
    earned_at DATETIME NOT NULL,
    seen TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_ach_user_code (user_id, code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tag VARCHAR(30) NOT NULL,
    target TINYINT NOT NULL DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_goals_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diary_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diary_id INT NOT NULL,
    user_id INT NOT NULL,
    mime VARCHAR(30) NOT NULL,
    size INT NOT NULL,
    data MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_photo_diary (diary_id),
    INDEX idx_photo_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_avatars (
    user_id INT NOT NULL PRIMARY KEY,
    mime VARCHAR(30) NOT NULL,
    size INT NOT NULL,
    data MEDIUMBLOB NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic VARCHAR(60) NOT NULL,
    message TEXT NULL,
    status ENUM('waiting','active','done','cancelled') NOT NULL DEFAULT 'waiting',
    counselor_id INT NULL,
    urgent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    feeling_level TINYINT NULL,
    duration VARCHAR(20) NULL,
    preferred_time VARCHAR(20) NULL,
    first_time TINYINT(1) NULL,
    preferred_counselor_id INT NULL,
    form_data MEDIUMTEXT NULL,
    INDEX idx_sr_user (user_id),
    INDEX idx_sr_status (status),
    INDEX idx_sr_counselor (counselor_id)
) ENGINE=InnoDB;

-- Akun admin awal. PASSWORD BAWAAN: admin123 -> WAJIB diganti setelah login pertama
-- (panel admin akan menampilkan peringatan selama password ini belum diubah).
INSERT INTO users (name, email, password, role)
SELECT 'Admin MoodJourney', 'admin@moodjourney.test',
       '$2y$10$9gTRGTvMdfwTvkaTCqULaeXgCz3EGiTKkZcMWrwgCSbUjJhBHpb7y', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@moodjourney.test');

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(160) NOT NULL,
    auth VARCHAR(60) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_endpoint (endpoint_hash),
    INDEX idx_push_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    open_on DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_at DATETIME NULL,
    INDEX idx_letters_user (user_id, open_on)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS butterfly_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stage VARCHAR(10) NOT NULL,
    mood VARCHAR(30) NULL,
    c1 CHAR(7) NOT NULL,
    c2 CHAR(7) NOT NULL,
    earned_on DATE NOT NULL,
    UNIQUE KEY uq_collection (user_id, stage, earned_on),
    INDEX idx_collection_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS challenge_done (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_start DATE NOT NULL,
    code VARCHAR(30) NOT NULL,
    done_on DATE NOT NULL,
    UNIQUE KEY uq_challenge (user_id, week_start)
) ENGINE=InnoDB;

REPLACE INTO app_meta (k, v) VALUES ('schema_version', '6');
