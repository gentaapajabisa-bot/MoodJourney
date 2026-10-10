<?php
$user = current_user();
if ($user) {
    redirect($user["role"] === "admin" ? "/admin/" : "/dashboard.php");
}
page_header("Mood Diary", ["nav" => "public", "desc" => "Ruang sederhana untuk mencatat perasaan, aktivitas, dan cerita harianmu."]);
?>
<section class="hero">

    <div class="hero-content">
        <p class="eyebrow">Mood diary pribadi</p>

        <h1>
            Kenali dirimu,<br>
            lewat setiap <span>cerita.</span>
        </h1>

        <p class="hero-description">
            MoodJourney adalah ruang sederhana untuk mencatat perasaan, aktivitas,
            dan cerita dari setiap harimu, lalu melihat polanya dari waktu ke waktu.
        </p>

        <div class="hero-actions">
            <a href="/register.php" class="btn">Mulai journey</a>
            <a href="/login.php" class="btn btn-outline">Saya sudah punya akun</a>
        </div>
    </div>

    <div class="hero-card" data-tilt="5">
        <div class="hero-stage" id="hero-3d" aria-hidden="true"
             data-moods='<?= h(json_encode(["😄", "🥰", "🙂", "😐", "😴", "😰", "😔", "😡"], JSON_UNESCAPED_UNICODE)) ?>'>
            <div class="emoji-big">😊</div>
        </div>
        <h2>Hari ini kamu merasa apa?</h2>
        <p>Pilih mood, ceritakan harimu, dan lihat perjalanan emosimu dari waktu ke waktu.</p>

        <div class="mood-row" aria-hidden="true">
            <?php foreach (["😄", "🥰", "🙂", "😐", "😴", "😰", "😔", "😡"] as $e): ?>
                <div class="mood-pill"><?= $e ?></div>
            <?php endforeach; ?>
        </div>
    </div>

</section>

<section class="features container">
    <div class="grid-3">
        <article class="feature" data-tilt="7">
            <h3>Catat dalam semenit</h3>
            <p>Pilih mood, atur seberapa kuat perasaannya, tandai aktivitas, lalu tulis cerita singkat kalau mau.</p>
        </article>
        <article class="feature" data-tilt="7">
            <h3>Lihat polanya</h3>
            <p>Grafik tren, kalender bulanan, dan hubungan antara aktivitas dengan mood-mu.</p>
        </article>
        <article class="feature" data-tilt="7">
            <h3>Tetap konsisten</h3>
            <p>Streak harian membantu kebiasaan mencatat bertahan, tanpa tekanan.</p>
        </article>
    </div>
</section>
<?php page_footer();
