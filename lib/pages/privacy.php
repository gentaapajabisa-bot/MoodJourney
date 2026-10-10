<?php
$user = current_user();
page_header("Privasi", ["nav" => nav_for($user)]);
?>
<div class="container narrow">

    <section class="page-title">
        <h1>Privasi</h1>
        <p class="muted">Apa yang disimpan MoodJourney, siapa yang bisa melihatnya, dan apa yang bisa kamu lakukan.</p>
    </section>

    <section class="card prose">
        <h2>Yang kami simpan</h2>
        <p>
            Nama, email, dan password (disimpan dalam bentuk hash, bukan teks asli), serta catatan mood yang kamu tulis:
            mood, intensitas, aktivitas, cerita, dan tanggal. Kalau kamu memakai chat, isi percakapannya juga disimpan.
        </p>

        <h2>Siapa yang bisa melihat</h2>
        <ul>
            <li><strong>Catatan diary</strong> tidak ditampilkan di panel admin. Admin hanya melihat ringkasan gabungan, seperti jumlah pengguna dan sebaran mood tanpa nama.</li>
            <li><strong>Foto</strong> pada catatan hanya bisa dilihat olehmu. Admin dan konselor tidak bisa membukanya. Foto dikecilkan di perangkatmu sebelum diunggah, sehingga data lokasi di dalam foto ikut hilang.</li>
            <li><strong>Chat dengan tim</strong> bisa dibaca admin. Kalau kamu mengajukan bicara dengan konselor, konselor yang menangani permintaanmu juga bisa membaca percakapan itu, dan namamu baru terlihat olehnya setelah ia mengambil permintaan. Jangan kirim data yang sangat pribadi lewat chat.</li>
            <li><strong>Tanda krisis.</strong> Pesan chat atau permintaan konselor yang memuat kata-kata tertentu terkait bahaya terhadap diri sendiri diberi penanda agar staf membacanya lebih dulu. Kalau catatan diary-mu memuat kata-kata seperti itu, kamu hanya akan melihat pesan dukungan, dan isi catatan tidak dikirim ke siapa pun.</li>
            <li><strong>Asisten FAQ</strong> menjawab dari panduan tetap, bukan AI, dan percakapannya tidak disimpan.</li>
            <li><strong>Ruang tenang</strong>: suara dibuat di perangkatmu dan tidak ada data yang dikirim.</li>
            <li>Pengelola teknis yang punya akses langsung ke database secara teknis bisa melihat isi data. Karena itu, hindari menulis informasi yang bisa membahayakanmu kalau bocor, seperti password atau nomor kartu.</li>
        </ul>

        <h2>Kendali ada padamu</h2>
        <ul>
            <li>Ubah atau hapus setiap catatan kapan saja.</li>
            <li>Unduh semua catatanmu sebagai CSV atau PDF dari halaman <a class="link" href="/profile.php">Profil</a>.</li>
            <li>Hapus akun beserta seluruh datanya dari halaman Profil. Penghapusan bersifat permanen.</li>
        </ul>

        <h2>Bukan layanan medis</h2>
        <p>
            MoodJourney membantu kamu mengenali pola, tetapi tidak memberi diagnosis atau perawatan.
            Lihat halaman <a class="link" href="/bantuan.php">Bantuan</a> untuk layanan yang bisa dihubungi.
        </p>
    </section>

</div>
<?php page_footer();
