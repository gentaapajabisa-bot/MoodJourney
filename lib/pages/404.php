<?php
page_header("Halaman tidak ditemukan", ["nav" => nav_for(current_user())]);
?>
<div class="container narrow">
    <section class="card empty-card">
        <p class="big-emoji" aria-hidden="true">🧭</p>
        <h1>Halaman tidak ditemukan</h1>
        <p class="muted">Alamat yang kamu buka tidak ada atau sudah dipindahkan.</p>
        <a class="btn" href="<?= current_user() ? h(home_for(current_user())) : "/" ?>">Kembali</a>
    </section>
</div>
<?php page_footer();
