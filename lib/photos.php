<?php
/**
 * Foto catatan: satu foto per catatan, disimpan di database (Vercel tidak punya penyimpanan
 * file permanen). Foto dikecilkan di browser sebelum diunggah; server memverifikasi ulang.
 */

const PHOTO_MAX_BYTES = 1500000;   // setelah dikompres di browser
const PHOTO_MAX_DIM = 4000;        // batas piksel sisi terpanjang
const PHOTO_MAX_PER_USER = 150;

/**
 * Proses unggahan dari $_FILES["photo"]. Mengembalikan null kalau tidak ada file,
 * ["error" => "..."] kalau gagal, atau ["mime" =>, "size" =>, "data" =>] kalau valid.
 */
function photo_read_upload(): ?array
{
    if (!isset($_FILES["photo"]) || !is_array($_FILES["photo"])) {
        return null;
    }
    $f = $_FILES["photo"];
    $err = (int) ($f["error"] ?? UPLOAD_ERR_NO_FILE);

    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ["error" => "Foto terlalu besar. Pilih foto yang lebih kecil."];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f["tmp_name"])) {
        return ["error" => "Foto gagal diunggah. Coba lagi."];
    }

    $size = (int) $f["size"];
    if ($size <= 0 || $size > PHOTO_MAX_BYTES) {
        return ["error" => "Ukuran foto maksimal " . round(PHOTO_MAX_BYTES / 1000000, 1) . " MB."];
    }

    $data = file_get_contents((string) $f["tmp_name"]);
    if ($data === false) {
        return ["error" => "Foto tidak bisa dibaca."];
    }

    $info = @getimagesizefromstring($data);
    $types = [IMAGETYPE_JPEG => "image/jpeg", IMAGETYPE_PNG => "image/png", IMAGETYPE_WEBP => "image/webp"];
    if (!$info || !isset($types[$info[2]])) {
        return ["error" => "Format foto harus JPG, PNG, atau WebP."];
    }
    if ($info[0] > PHOTO_MAX_DIM || $info[1] > PHOTO_MAX_DIM || $info[0] < 1 || $info[1] < 1) {
        return ["error" => "Dimensi foto terlalu besar."];
    }

    return ["mime" => $types[$info[2]], "size" => strlen($data), "data" => $data];
}

function photo_count(int $userId): int
{
    return (int) db_value("SELECT COUNT(*) FROM diary_photos WHERE user_id = ?", "i", [$userId], 0);
}

/** Simpan atau ganti foto sebuah catatan. */
function photo_save(int $userId, int $diaryId, array $photo): void
{
    db_exec("DELETE FROM diary_photos WHERE diary_id = ? AND user_id = ?", "ii", [$diaryId, $userId]);
    db_exec(
        "INSERT INTO diary_photos (diary_id, user_id, mime, size, data) VALUES (?, ?, ?, ?, ?)",
        "iisis",
        [$diaryId, $userId, $photo["mime"], $photo["size"], $photo["data"]]
    );
}

function photo_delete_for_diary(int $userId, int $diaryId): void
{
    db_exec("DELETE FROM diary_photos WHERE diary_id = ? AND user_id = ?", "ii", [$diaryId, $userId]);
}

/** Tambahkan kunci "photo_id" ke tiap entri (null bila tidak ada foto). */
function attach_photo_ids(array &$entries, int $userId): void
{
    $ids = array_map(fn($e) => (int) $e["id"], $entries);
    foreach ($entries as &$e) {
        $e["photo_id"] = null;
    }
    unset($e);
    if (!$ids) {
        return;
    }

    $marks = implode(",", array_fill(0, count($ids), "?"));
    $rows = db_all(
        "SELECT id, diary_id FROM diary_photos WHERE user_id = ? AND diary_id IN ($marks)",
        "i" . str_repeat("i", count($ids)),
        array_merge([$userId], $ids)
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r["diary_id"]] = (int) $r["id"];
    }
    foreach ($entries as &$e) {
        $e["photo_id"] = $map[(int) $e["id"]] ?? null;
    }
    unset($e);
}
