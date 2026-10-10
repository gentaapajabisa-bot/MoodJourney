<?php
$user = current_user();
page_header("Privasi", ["nav" => $user ? ($user["role"] === "admin" ? "admin" : "app") : "public"]);
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
            <li><strong>Chat</strong> dengan dukungan bisa dibaca oleh admin. Jangan kirim data yang sangat pribadi lewat chat.</li>
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
