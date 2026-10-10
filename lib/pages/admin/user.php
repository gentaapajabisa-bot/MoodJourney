<?php
$admin = require_admin();

if (!is_post()) {
    redirect("/admin/");
}
csrf_verify("/admin/");

$action = (string) ($_POST["action"] ?? "");
$userId = (int) ($_POST["user_id"] ?? 0);
$requestId = (int) ($_POST["request_id"] ?? 0);

/* Semua aksi pada pengguna hanya berlaku untuk akun biasa, tidak pernah untuk admin. */
$target = $userId > 0
    ? db_one("SELECT id, name, email, role, is_active FROM users WHERE id = ? AND role IN ('user', 'counselor') LIMIT 1", "i", [$userId])
    : null;

switch ($action) {

    case "toggle_active":
        if (!$target) {
            flash("error", "Pengguna tidak ditemukan.");
            break;
        }
        $new = (int) $target["is_active"] === 1 ? 0 : 1;
        db_exec("UPDATE users SET is_active = ? WHERE id = ?", "ii", [$new, (int) $target["id"]]);
        flash("success", $target["name"] . ($new ? " diaktifkan kembali." : " dinonaktifkan. Sesi login-nya langsung tidak berlaku."));
        break;

    case "reset_link":
        if (!$target) {
            flash("error", "Pengguna tidak ditemukan.");
            break;
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash("sha256", $token);

        $updated = 0;
        if ($requestId > 0) {
            [$updated] = db_exec(
                "UPDATE password_resets SET token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR), handled_at = NOW()
                 WHERE id = ? AND user_id = ? AND token_hash IS NULL",
                "sii",
                [$hash, $requestId, (int) $target["id"]]
            );
        }
        if ($updated === 0) {
            db_exec(
                "INSERT INTO password_resets (user_id, token_hash, expires_at, handled_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())",
                "is",
                [(int) $target["id"], $hash]
            );
        }

        $base = app_base_url();
        if ($base === "") {
            $scheme = (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "https") === "http") ? "http" : "https";
            $base = $scheme . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost");
        }
        $_SESSION["reset_link"] = ["name" => $target["name"], "url" => $base . "/reset.php?token=" . $token];
        break;

    case "dismiss_request":
        if ($requestId > 0) {
            db_exec("UPDATE password_resets SET handled_at = NOW() WHERE id = ? AND token_hash IS NULL", "i", [$requestId]);
            flash("success", "Permintaan diabaikan.");
        }
        break;

    case "create_counselor":
        $cname = trim((string) ($_POST["name"] ?? ""));
        $cemail = mb_strtolower(trim((string) ($_POST["email"] ?? "")));
        if ($cname === "" || mb_strlen($cname) > 100 || !filter_var($cemail, FILTER_VALIDATE_EMAIL) || mb_strlen($cemail) > 150) {
            flash("error", "Isi nama dan email konselor dengan benar.");
            break;
        }
        if (db_one("SELECT id FROM users WHERE email = ? LIMIT 1", "s", [$cemail])) {
            flash("error", "Email itu sudah terdaftar.");
            break;
        }
        // password acak yang tidak diketahui siapa pun; konselor membuat sendiri lewat tautan
        [, $newId] = db_exec(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'counselor')",
            "sss",
            [$cname, $cemail, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]
        );
        $token = bin2hex(random_bytes(32));
        db_exec(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, handled_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())",
            "is",
            [$newId, hash("sha256", $token)]
        );
        $base = app_base_url();
        if ($base === "") {
            $scheme = (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "https") === "http") ? "http" : "https";
            $base = $scheme . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost");
        }
        $_SESSION["reset_link"] = ["name" => $cname . " (konselor, berlaku 24 jam)", "url" => $base . "/reset.php?token=" . $token];
        flash("success", "Akun konselor dibuat. Kirim tautan di bawah agar ia membuat password sendiri.");
        break;

    case "delete":
        if (!$target || $target["role"] !== "user") {
            flash("error", "Hanya akun pengguna biasa yang bisa dihapus. Konselor cukup dinonaktifkan.");
            break;
        }
        delete_user_data((int) $target["id"]);
        flash("success", "Akun " . $target["name"] . " dan seluruh datanya sudah dihapus.");
        break;

    default:
        flash("error", "Aksi tidak dikenal.");
}

redirect("/admin/");
