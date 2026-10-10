<?php
$user = current_user();
page_header("Bantuan", ["nav" => $user ? ($user["role"] === "admin" ? "admin" : "app") : "public"]);
?>
<div class="container narrow">

    <section class="page-title">
        <h1>Bantuan dan layanan darurat</h1>
        <p class="muted">
            MoodJourney adalah buku harian, bukan layanan medis dan tidak bisa mendiagnosis apa pun.
            Kalau kamu butuh bicara dengan seseorang, layanan di bawah ini bisa dihubungi.
        </p>
    </section>

    <aside class="support-card">
        <p class="support-title">Kalau kamu dalam bahaya atau berpikir untuk menyakiti dirimu</p>
        <p>
            Hubungi <strong>112</strong> (nomor darurat nasional) atau datang ke IGD rumah sakit terdekat.
            Kalau memungkinkan, kabari orang yang kamu percaya supaya kamu tidak sendirian.
        </p>
    </aside>

    <section class="card">
        <h2>Layanan gratis di Indonesia</h2>

        <ul class="resource-list">
            <li>
                <strong>Healing119.id (Kementerian Kesehatan)</strong>
                <p>Konsultasi kesehatan jiwa lewat telepon dan chat, termasuk krisis. Telepon <strong><a class="link" href="tel:119">119</a> lalu tekan ekstensi 8</strong>,
                    atau buka <a class="link" href="https://healing119.id" target="_blank" rel="noopener noreferrer">healing119.id</a>.
                    Layanan ini ramai; lewat situs biasanya antreannya lebih pendek.</p>
            </li>
            <li>
                <strong>Halo Kemenkes</strong>
                <p>Informasi layanan kesehatan di <a class="link" href="tel:1500567">1500-567</a>.</p>
            </li>
            <li>
                <strong>Puskesmas dan rumah sakit terdekat</strong>
                <p>Banyak Puskesmas punya layanan konseling dan bisa merujuk ke psikolog klinis atau psikiater.</p>
            </li>
        </ul>

        <p class="muted">
            Nomor dan layanan bisa berubah. Kalau salah satunya tidak tersambung, cek situs resmi
            <a class="link" href="https://kemkes.go.id" target="_blank" rel="noopener noreferrer">kemkes.go.id</a>.
        </p>
    </section>

    <section class="card">
        <h2>Tanda untuk mencari bantuan profesional</h2>
        <p class="muted">Pertimbangkan bicara dengan psikolog atau dokter kalau kamu mengalami hal-hal ini selama dua minggu atau lebih:</p>
        <ul class="plain-list">
            <li>Merasa sedih, kosong, atau cemas hampir setiap hari</li>
            <li>Sulit tidur, atau tidur jauh lebih banyak dari biasanya</li>
            <li>Kehilangan minat pada hal yang biasanya kamu nikmati</li>
            <li>Sulit menjalani kuliah, kerja, atau hubungan dengan orang sekitar</li>
        </ul>
    </section>

    <section class="card">
        <h2>Masalah dengan aplikasi?</h2>
        <p class="muted">
            Soal akun, password, atau fitur, kamu bisa
            <?= $user && $user["role"] !== "admin" ? '<a class="link" href="/chat.php">chat dengan admin</a>' : '<a class="link" href="/forgot.php">atur ulang password</a> atau hubungi admin' ?>.
            Admin bukan tenaga kesehatan jiwa.
        </p>
    </section>

</div>
<?php page_footer();
