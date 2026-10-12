<?php
/**
 * Foto catatan (maksimal 4 per catatan) dan foto profil.
 * Disimpan di database karena Vercel tidak punya penyimpanan file permanen.
 * Foto dikecilkan di browser sebelum diunggah; server memverifikasi ulang.
 *
 * Catatan: Vercel membatasi isi permintaan sekitar 4,5 MB. Browser mengompres tiap foto
 * hingga di bawah ~0,9 MB, jadi 4 foto baru masih muat dalam satu pengiriman.
 */

const PHOTO_MAX_BYTES = 1500000;   // per foto, setelah dikompres di browser
const PHOTO_MAX_DIM = 4000;        // batas piksel sisi terpanjang
const PHOTO_MAX_PER_USER = 150;
const PHOTO_MAX_PER_ENTRY = 4;
const PHOTO_MAX_BATCH_BYTES = 4000000; // total foto baru dalam satu pengiriman

const AVATAR_MAX_BYTES = 300000;
const AVATAR_MAX_DIM = 1024;

/** Validasi satu entri $_FILES. Mengembalikan ["error" => ...] atau ["mime", "size", "data"]. */
function photo_validate_file(array $f, int $maxBytes = PHOTO_MAX_BYTES, int $maxDim = PHOTO_MAX_DIM): array
{
    $err = (int) ($f["error"] ?? UPLOAD_ERR_NO_FILE);

    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ["error" => "Foto terlalu besar. Pilih foto yang lebih kecil."];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f["tmp_name"] ?? ""))) {
        return ["error" => "Foto gagal diunggah. Coba lagi."];
    }

    $size = (int) $f["size"];
    if ($size <= 0 || $size > $maxBytes) {
        return ["error" => "Ukuran foto maksimal " . round($maxBytes / 1000000, 1) . " MB."];
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
    if ($info[0] > $maxDim || $info[1] > $maxDim || $info[0] < 1 || $info[1] < 1) {
        return ["error" => "Dimensi foto terlalu besar."];
    }

    return ["mime" => $types[$info[2]], "size" => strlen($data), "data" => $data];
}

/**
 * Baca satu unggahan dari $_FILES[$field]. null = tidak ada file.
 * @return array|null ["error"=>...] atau ["mime","size","data"]
 */
function photo_read_single(string $field, int $maxBytes, int $maxDim): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || is_array($f["error"] ?? null)) {
        return null;
    }
    if ((int) ($f["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return photo_validate_file($f, $maxBytes, $maxDim);
}

/**
 * Baca banyak unggahan dari $_FILES[$field][] (input multiple).
 * @return array{photos:array<int,array>, errors:string[]}
 */
function photo_read_uploads(string $field = "photos"): array
{
    $out = ["photos" => [], "errors" => []];
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || !is_array($f["error"] ?? null)) {
        return $out;
    }

    $total = 0;
    foreach (array_keys($f["error"]) as $i) {
        if ((int) $f["error"][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $res = photo_validate_file([
            "error"    => $f["error"][$i],
            "tmp_name" => $f["tmp_name"][$i] ?? "",
            "size"     => $f["size"][$i] ?? 0,
        ]);
        if (isset($res["error"])) {
            $out["errors"][] = $res["error"];
            continue;
        }
        $total += $res["size"];
        $out["photos"][] = $res;
    }

    if ($total > PHOTO_MAX_BATCH_BYTES) {
        $out["errors"][] = "Total ukuran foto terlalu besar. Pilih lebih sedikit foto atau yang lebih kecil.";
        $out["photos"] = [];
    }
    return $out;
}

function photo_count(int $userId): int
{
    return (int) db_value("SELECT COUNT(*) FROM diary_photos WHERE user_id = ?", "i", [$userId], 0);
}

/** ID foto milik sebuah catatan, urut dari yang pertama diunggah. */
function photo_ids_for_diary(int $userId, int $diaryId): array
{
    return array_map(
        fn($r) => (int) $r["id"],
        db_all("SELECT id FROM diary_photos WHERE diary_id = ? AND user_id = ? ORDER BY id ASC", "ii", [$diaryId, $userId])
    );
}

/** Tambahkan satu foto ke catatan. */
function photo_add(int $userId, int $diaryId, array $photo): void
{
    db_exec(
        "INSERT INTO diary_photos (diary_id, user_id, mime, size, data) VALUES (?, ?, ?, ?, ?)",
        "iisis",
        [$diaryId, $userId, $photo["mime"], $photo["size"], $photo["data"]]
    );
}

/** Hapus foto tertentu (harus milik pengguna dan catatan itu). */
function photo_delete_ids(int $userId, int $diaryId, array $ids): void
{
    foreach ($ids as $id) {
        db_exec("DELETE FROM diary_photos WHERE id = ? AND diary_id = ? AND user_id = ?", "iii", [(int) $id, $diaryId, $userId]);
    }
}

function photo_delete_for_diary(int $userId, int $diaryId): void
{
    db_exec("DELETE FROM diary_photos WHERE diary_id = ? AND user_id = ?", "ii", [$diaryId, $userId]);
}

/** Tambahkan "photo_ids" (array) dan "photo_id" (foto pertama atau null) ke tiap entri. */
function attach_photo_ids(array &$entries, int $userId): void
{
    $ids = array_map(fn($e) => (int) $e["id"], $entries);
    foreach ($entries as &$e) {
        $e["photo_ids"] = [];
        $e["photo_id"] = null;
    }
    unset($e);
    if (!$ids) {
        return;
    }

    $marks = implode(",", array_fill(0, count($ids), "?"));
    $rows = db_all(
        "SELECT id, diary_id FROM diary_photos WHERE user_id = ? AND diary_id IN ($marks) ORDER BY id ASC",
        "i" . str_repeat("i", count($ids)),
        array_merge([$userId], $ids)
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r["diary_id"]][] = (int) $r["id"];
    }
    foreach ($entries as &$e) {
        $e["photo_ids"] = $map[(int) $e["id"]] ?? [];
        $e["photo_id"] = $e["photo_ids"][0] ?? null;
    }
    unset($e);
}

/* ---------- Foto profil ---------- */

/** Simpan atau ganti foto profil. */
function avatar_save(int $userId, array $photo): void
{
    db_exec(
        "REPLACE INTO user_avatars (user_id, mime, size, data, updated_at) VALUES (?, ?, ?, ?, NOW())",
        "isis",
        [$userId, $photo["mime"], $photo["size"], $photo["data"]]
    );
    db_exec("UPDATE users SET avatar_v = ? WHERE id = ?", "ii", [time(), $userId]);
}

function avatar_delete(int $userId): void
{
    db_exec("DELETE FROM user_avatars WHERE user_id = ?", "i", [$userId]);
    db_exec("UPDATE users SET avatar_v = NULL WHERE id = ?", "i", [$userId]);
}

/** Foto profil bundar, atau lingkaran berinisial kalau belum ada foto. */
function avatar_html(array $user, int $size = 32, string $class = ""): string
{
    $style = "--avatar-size:" . (int) $size . "px";
    $cls = trim("avatar " . $class);
    if (!empty($user["avatar_v"])) {
        return '<img class="' . h($cls) . '" style="' . $style . '" src="/avatar.php?v=' . (int) $user["avatar_v"]
             . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '">';
    }
    $initial = mb_strtoupper(mb_substr(trim((string) ($user["name"] ?? "?")), 0, 1)) ?: "?";
    return '<span class="' . h($cls) . ' avatar-initial" style="' . $style . '" aria-hidden="true">' . h($initial) . '</span>';
}

/** Foto profil pengguna lain untuk panel admin (hanya admin yang boleh mengambilnya lewat /avatar.php?u=). */
function admin_avatar_html(array $u, int $size = 40, string $class = ""): string
{
    $style = "--avatar-size:" . (int) $size . "px";
    $cls = trim("avatar " . $class);
    if (!empty($u["avatar_v"])) {
        return '<img class="' . h($cls) . '" style="' . $style . '" loading="lazy" src="/avatar.php?u=' . (int) $u["id"] . '&amp;v=' . (int) $u["avatar_v"]
             . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '">';
    }
    $initial = mb_strtoupper(mb_substr(trim((string) ($u["name"] ?? "?")), 0, 1)) ?: "?";
    return '<span class="' . h($cls) . ' avatar-initial" style="' . $style . '" aria-hidden="true">' . h($initial) . '</span>';
}
