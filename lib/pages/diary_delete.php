<?php
$user = require_login();

if (!is_post()) {
    redirect("/history.php");
}

$return = safe_return((string) ($_POST["return"] ?? ""), "/history.php");
csrf_verify($return);

$id = (int) ($_POST["id"] ?? 0);
[$affected] = db_exec("DELETE FROM diary WHERE id = ? AND user_id = ?", "ii", [$id, (int) $user["id"]]);

flash($affected > 0 ? "success" : "error", $affected > 0 ? "Catatan dihapus." : "Catatan tidak ditemukan.");
redirect($return);
