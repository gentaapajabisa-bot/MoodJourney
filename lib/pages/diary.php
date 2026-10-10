<?php
$user = require_member();
$userId = (int) $user["id"];

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$entry = null;

if ($id > 0) {
    $entry = db_one("SELECT * FROM diary WHERE id = ? AND user_id = ? LIMIT 1", "ii", [$id, $userId]);
    if (!$entry) {
        flash("error", "Catatan tidak ditemukan.");
        redirect("/history.php");
    }
}

$today = date("Y-m-d");
$errors = [];

$existingPhotoId = null;
if ($entry) {
    $existingPhotoId = db_value(
        "SELECT id FROM diary_photos WHERE diary_id = ? AND user_id = ? LIMIT 1",
        "ii",
        [$id, $userId]
    );
}

$form = [
    "mood"      => $entry["mood"] ?? "🙂",
    "intensity" => (int) ($entry["intensity"] ?? 3),
    "tags"      => tags_to_array($entry["tags"] ?? ""),
    "activity"  => $entry["activity"] ?? "",
    "note"      => $entry["note"] ?? "",
    "date"      => $entry["diary_date"] ?? (isset($_GET["date"]) && valid_date((string) $_GET["date"]) && $_GET["date"] <= $today ? $_GET["date"] : $today),
];

if (is_post()) {
    csrf_verify("/diary.php" . ($id ? "?id=$id" : ""));

    $form["mood"]      = (string) ($_POST["mood"] ?? "");
    $form["intensity"] = (int) ($_POST["intensity"] ?? 3);
    $form["tags"]      = tags_to_array(tags_from_input($_POST["tags"] ?? []));
    $form["activity"]  = trim((string) ($_POST["activity"] ?? ""));
    $form["note"]      = trim((string) ($_POST["note"] ?? ""));
    $form["date"]      = (string) ($_POST["date"] ?? $today);

    if (!isset(MOODS[$form["mood"]])) {
        $errors[] = "Pilih salah satu mood.";
    }
    if ($form["intensity"] < 1 || $form["intensity"] > 5) {
        $form["intensity"] = 3;
    }
    if (!valid_date($form["date"])) {
        $errors[] = "Tanggal tidak valid.";
    } elseif ($form["date"] > $today) {
        $errors[] = "Tanggal tidak boleh di masa depan.";
    }
    if (mb_strlen($form["activity"]) > 150) {
        $errors[] = "Aktivitas maksimal 150 karakter.";
    }
    if (mb_strlen($form["note"]) > 3000) {
        $errors[] = "Cerita maksimal 3000 karakter.";
    }

    $photo = photo_read_upload();
    if ($photo !== null && isset($photo["error"])) {
        $errors[] = $photo["error"];
        $photo = null;
    } elseif ($photo !== null && !$existingPhotoId && photo_count($userId) >= PHOTO_MAX_PER_USER) {
        $errors[] = "Batas jumlah foto tercapai. Hapus beberapa foto lama dulu.";
        $photo = null;
    }

    if (!$errors) {
        $stageBefore = journey_load($userId)["stage"];
        $tags = implode(",", $form["tags"]);
        if ($entry) {
            db_exec(
                "UPDATE diary SET mood = ?, intensity = ?, tags = ?, activity = ?, note = ?, diary_date = ?, updated_at = NOW()
                 WHERE id = ? AND user_id = ?",
                "sissssii",
                [$form["mood"], $form["intensity"], $tags, $form["activity"], $form["note"], $form["date"], $id, $userId]
            );
            $savedId = $id;
            flash("success", "Catatan diperbarui.");
        } else {
            [, $savedId] = db_exec(
                "INSERT INTO diary (user_id, mood, intensity, tags, activity, note, diary_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                "isissss",
                [$userId, $form["mood"], $form["intensity"], $tags, $form["activity"], $form["note"], $form["date"]]
            );
            flash("success", "Catatan tersimpan.");
        }

        if ($photo !== null) {
            photo_save($userId, (int) $savedId, $photo);
        } elseif ($entry && !empty($_POST["remove_photo"])) {
            photo_delete_for_diary($userId, (int) $savedId);
        }

        // tanda krisis di cerita: tampilkan dukungan dengan lembut (tidak ada yang dikirim ke staf)
        if (crisis_detect($form["note"] . " " . $form["activity"])) {
            flash("info", crisis_message() . " Kamu juga bisa mengajukan bicara dengan konselor di halaman Dukungan.");
        }

        $stageAfter = journey_load($userId)["stage"];
        if (array_search($stageAfter, array_keys(BUTTERFLY_STAGES), true) > array_search($stageBefore, array_keys(BUTTERFLY_STAGES), true)) {
            flash("info", journey_stage_up_text($stageAfter));
        }

        $newAchievements = check_achievements($userId);
        if ($newAchievements) {
            flash("info", achievements_flash_text($newAchievements));
        }
        redirect("/dashboard.php");
    }
}

page_header($entry ? "Ubah catatan" : "Catat mood", ["active" => "diary"]);
?>
<div class="container narrow">
    <section class="card">

        <h1><?= $entry ? "Ubah catatan" : "Catat harimu" ?></h1>
        <p class="muted">Nggak perlu panjang. Tulis apa yang kamu rasakan.</p>

        <?php if ($errors): ?>
            <div class="notice notice-error" role="alert">
                <?= implode("<br>", array_map("h", $errors)) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="diary-form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if ($entry): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

            <fieldset class="form-group">
                <legend>Mood kamu</legend>
                <div class="mood-picker">
                    <?php foreach (MOODS as $emoji => $info): ?>
                        <label class="mood-option">
                            <input type="radio" name="mood" value="<?= h($emoji) ?>" data-score="<?= (int) $info["score"] ?>" <?= $form["mood"] === $emoji ? "checked" : "" ?> required>
                            <span class="mood-face" aria-hidden="true"><?= h($emoji) ?></span>
                            <span class="mood-name"><?= h($info["label"]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="intensity">Seberapa kuat perasaan itu? <output id="intensity-out" for="intensity"><?= h(INTENSITY_LABELS[$form["intensity"]]) ?></output></label>
                <input class="range" type="range" id="intensity" name="intensity" min="1" max="5" step="1"
                       value="<?= $form["intensity"] ?>" data-labels='<?= h(json_encode(array_values(INTENSITY_LABELS))) ?>'>
                <div class="range-scale" aria-hidden="true"><span>Ringan</span><span>Kuat</span></div>
            </div>

            <fieldset class="form-group">
                <legend>Apa yang kamu lakukan hari ini?</legend>
                <div class="chip-row">
                    <?php foreach (TAGS as $key => $label): ?>
                        <label class="chip">
                            <input type="checkbox" name="tags[]" value="<?= h($key) ?>" <?= in_array($key, $form["tags"], true) ? "checked" : "" ?>>
                            <span><?= h($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="activity">Aktivitas lain <span class="muted">(opsional)</span></label>
                <input class="form-control" type="text" id="activity" name="activity" maxlength="150"
                       value="<?= h($form["activity"]) ?>" placeholder="Contoh: Presentasi proyek, nonton film">
            </div>

            <div class="prompt-box" id="prompt-box"
                 data-prompts='<?= h(json_encode(PROMPTS, JSON_UNESCAPED_UNICODE)) ?>'
                 data-seed="<?= daily_prompt_seed() ?>">
                <p class="prompt-label">Bingung mau menulis apa?</p>
                <p class="prompt-text" id="prompt-text" aria-live="polite"></p>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline btn-small" id="prompt-use">Pakai pertanyaan ini</button>
                    <button type="button" class="btn btn-outline btn-small" id="prompt-next">Ganti pertanyaan</button>
                </div>
            </div>

            <div class="form-group">
                <label for="note">Ceritakan harimu <span class="muted">(opsional)</span></label>
                <textarea class="form-control" id="note" name="note" rows="6" maxlength="3000"
                          placeholder="Hari ini aku..." data-counter="note-count"><?= h($form["note"]) ?></textarea>
                <small class="muted"><span id="note-count">0</span>/3000</small>
            </div>

            <fieldset class="form-group photo-field">
                <legend>Foto <span class="muted">(opsional, satu per catatan)</span></legend>

                <?php if ($existingPhotoId): ?>
                    <div class="photo-current">
                        <img src="/photo.php?id=<?= (int) $existingPhotoId ?>" alt="Foto catatan saat ini" loading="lazy">
                        <label class="check-row">
                            <input type="checkbox" name="remove_photo" value="1">
                            <span>Hapus foto ini</span>
                        </label>
                    </div>
                <?php endif; ?>

                <input class="form-control" type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/webp,image/*"
                       aria-describedby="photo-hint">
                <small id="photo-hint" class="muted block">Foto dikecilkan otomatis di perangkatmu dan hanya bisa dilihat olehmu.
                    <?= $existingPhotoId ? "Memilih foto baru akan mengganti yang lama." : "" ?></small>
                <div class="photo-preview" id="photo-preview" hidden>
                    <img id="photo-preview-img" alt="Pratinjau foto yang dipilih">
                    <button type="button" class="btn btn-outline btn-small" id="photo-clear">Batal pilih foto</button>
                </div>
                <p class="muted" id="photo-status" aria-live="polite"></p>
            </fieldset>

            <div class="form-group">
                <label for="date">Tanggal</label>
                <input class="form-control" type="date" id="date" name="date" value="<?= h($form["date"]) ?>" max="<?= $today ?>" required>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit"><?= $entry ? "Simpan perubahan" : "Simpan mood" ?></button>
                <a class="btn btn-outline" href="/dashboard.php">Batal</a>
            </div>
        </form>

    </section>
</div>
<?php page_footer();
