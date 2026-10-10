<?php
/**
 * Asisten FAQ berbasis aturan (bukan AI): mencocokkan kata kunci ke jawaban yang sudah ditulis.
 * Pertanyaan yang tidak dipahami diarahkan ke tim atau konselor.
 */

const BOT_KB = [
    [
        "id" => "catat",
        "q" => "Bagaimana cara mencatat mood?",
        "keys" => ["catat", "mencatat", "tambah catatan", "buat catatan", "isi mood", "input mood", "nulis mood"],
        "a" => "Klik tombol \"Catat mood\", pilih emoji yang paling pas, geser seberapa kuat perasaannya, tandai aktivitas, lalu tulis cerita singkat kalau mau. Cerita dan aktivitas boleh dikosongkan. Kamu juga bisa mengisi tanggal yang sudah lewat.",
        "links" => [["Catat mood", "/diary.php"]],
    ],
    [
        "id" => "ubah",
        "q" => "Bagaimana mengubah atau menghapus catatan?",
        "keys" => ["ubah catatan", "edit catatan", "hapus catatan", "salah catat", "salah pilih", "koreksi catatan", "ganti catatan"],
        "a" => "Buka Riwayat atau Dashboard, lalu klik \"Ubah\" atau \"Hapus\" pada catatan yang dimaksud. Catatan yang dihapus tidak bisa dikembalikan.",
        "links" => [["Buka riwayat", "/history.php"]],
    ],
    [
        "id" => "password",
        "q" => "Lupa password atau ingin menggantinya",
        "keys" => ["lupa password", "lupa kata sandi", "ganti password", "ubah password", "reset password", "ganti kata sandi", "tidak bisa masuk", "gak bisa login", "tidak bisa login"],
        "a" => "Kalau masih bisa masuk, ganti di Profil → Ubah password. Kalau lupa, di halaman masuk klik \"Lupa password?\". Admin atau konselor akan mengirimkan tautan untuk membuat password baru.",
        "links" => [["Buka profil", "/profile.php"]],
    ],
    [
        "id" => "hapusakun",
        "q" => "Bagaimana menghapus akun dan data saya?",
        "keys" => ["hapus akun", "hapus data", "tutup akun", "delete akun", "hapus semua"],
        "a" => "Buka Profil lalu cari bagian \"Hapus akun\". Semua catatan, foto, dan percakapanmu akan dihapus permanen. Unduh datamu lebih dulu kalau masih mau menyimpannya.",
        "links" => [["Buka profil", "/profile.php"]],
    ],
    [
        "id" => "ekspor",
        "q" => "Bagaimana mengunduh catatan saya?",
        "keys" => ["unduh", "download", "ekspor", "export", "csv", "pdf", "simpan catatan", "cetak"],
        "a" => "Di Riwayat ada tombol \"Unduh CSV\" dan \"Cetak / PDF\". Filter di riwayat ikut terbawa, jadi kamu bisa mengunduh sebagian saja. Foto tidak ikut dalam ekspor.",
        "links" => [["Buka riwayat", "/history.php"]],
    ],
    [
        "id" => "streak",
        "q" => "Apa itu streak?",
        "keys" => ["streak", "beruntun", "berturut"],
        "a" => "Streak adalah jumlah hari berturut-turut kamu mencatat mood. Kalau satu hari terlewat, hitungannya mulai lagi, tapi streak terpanjangmu tetap tersimpan. Tidak perlu dikejar sampai tertekan. Mencatat sesekali pun tetap berguna.",
        "links" => [],
    ],
    [
        "id" => "target",
        "q" => "Bagaimana cara kerja target?",
        "keys" => ["target", "goal", "kebiasaan", "habit"],
        "a" => "Di Wawasan → Target, pilih aktivitas dan jumlah hari per minggu. Progresnya dihitung dari tag aktivitas di catatanmu, Senin sampai Minggu. Satu hari dihitung sekali.",
        "links" => [["Buka target", "/target.php"]],
    ],
    [
        "id" => "badge",
        "q" => "Apa itu pencapaian?",
        "keys" => ["pencapaian", "badge", "lencana", "achievement"],
        "a" => "Pencapaian adalah penanda perjalananmu, misalnya 10 catatan atau 7 hari beruntun. Ini bukan kompetisi dan hanya kamu yang melihatnya.",
        "links" => [["Lihat pencapaian", "/pencapaian.php"]],
    ],
    [
        "id" => "statistik",
        "q" => "Bagaimana membaca grafik dan statistik?",
        "keys" => ["grafik", "statistik", "tren", "kalender", "rata-rata", "rata rata", "skor", "lanskap"],
        "a" => "Setiap mood punya skor 1 (berat) sampai 5 (sangat baik). Grafik tren memakai rata-rata skor per hari. Pola aktivitas dan mood hanya menunjukkan kecenderungan, bukan sebab-akibat, dan baru muncul setelah suatu aktivitas tercatat minimal 3 kali.",
        "links" => [["Buka statistik", "/statistics.php"]],
    ],
    [
        "id" => "privasi",
        "q" => "Siapa yang bisa melihat catatan saya?",
        "keys" => ["privasi", "siapa yang bisa lihat", "aman", "rahasia", "dibaca admin", "dilihat", "keamanan data"],
        "a" => "Catatan diary dan fotomu tidak ditampilkan di panel admin atau konselor. Yang bisa dibaca staf adalah isi chat dengan tim. Pengelola teknis yang punya akses langsung ke database secara teknis bisa melihat data, jadi hindari menulis hal yang bisa membahayakanmu jika bocor.",
        "links" => [["Baca halaman privasi", "/privasi.php"]],
    ],
    [
        "id" => "pengingat",
        "q" => "Bagaimana mengaktifkan pengingat?",
        "keys" => ["pengingat", "notifikasi", "reminder", "ingatkan", "alarm"],
        "a" => "Di Profil ada bagian Pengingat. Pengingat browser muncul pada jam pilihanmu saat MoodJourney terbuka, dan pengingat email dikirim malam hari kalau fiturnya diaktifkan pengelola.",
        "links" => [["Atur pengingat", "/profile.php#reminders"]],
    ],
    [
        "id" => "pasang",
        "q" => "Bisakah dipasang sebagai aplikasi?",
        "keys" => ["pasang aplikasi", "install", "layar utama", "aplikasi hp", "pwa", "offline"],
        "a" => "Bisa. Di Chrome atau Edge, buka Profil lalu klik \"Pasang aplikasi\". Di iPhone, buka menu Bagikan di Safari lalu pilih \"Tambah ke Layar Utama\". Aplikasi tetap butuh internet untuk memuat catatanmu.",
        "links" => [["Buka profil", "/profile.php#reminders"]],
    ],
    [
        "id" => "tema",
        "q" => "Bagaimana mengganti tema gelap?",
        "keys" => ["tema", "mode gelap", "dark mode", "gelap", "terang"],
        "a" => "Klik ikon bulan atau matahari di pojok kanan atas bilah navigasi. Pilihanmu diingat di perangkat ini.",
        "links" => [],
    ],
    [
        "id" => "foto",
        "q" => "Bagaimana menambahkan foto ke catatan?",
        "keys" => ["foto", "gambar", "upload", "unggah", "lampiran", "picture"],
        "a" => "Di form catatan ada bagian \"Foto\". Pilih satu foto dan ukurannya dikecilkan otomatis. Satu catatan bisa punya satu foto. Foto hanya bisa dilihat olehmu.",
        "links" => [["Catat mood", "/diary.php"]],
    ],
    [
        "id" => "musik",
        "q" => "Ada musik atau suara yang menenangkan?",
        "keys" => ["musik", "suara", "tenang", "relaks", "rileks", "napas", "pernapasan", "hujan", "ombak", "tidur", "cemas", "panik"],
        "a" => "Ada halaman \"Tenang\" dengan suara hujan, ombak, angin, dan dengung lembut yang bisa kamu campur sendiri, lengkap dengan timer dan pemandu napas. Suaranya dibuat langsung di browser, jadi tidak ada data yang dikirim ke mana pun.",
        "links" => [["Buka ruang tenang", "/tenang.php"]],
    ],
    [
        "id" => "chat",
        "q" => "Bagaimana menghubungi admin atau konselor?",
        "keys" => ["admin", "konselor", "psikolog", "curhat", "ngobrol", "bicara dengan", "hubungi", "chat", "bantuan orang"],
        "a" => "Untuk soal aplikasi, kirim pesan lewat chat dengan tim. Kalau kamu ingin bicara dengan konselor, ajukan permintaan di bagian \"Bicara dengan konselor\" pada halaman Dukungan. Permintaanmu akan ditangani sesuai jam layanan, jadi balasan mungkin tidak langsung.",
        "links" => [["Chat dengan tim", "/chat.php"], ["Ajukan konselor", "/dukungan.php#konselor"]],
    ],
];

/** Jawaban bot untuk satu pertanyaan. */
function bot_answer(string $question): array
{
    if (crisis_detect($question)) {
        return [
            "crisis"  => true,
            "reply"   => crisis_message(),
            "links"   => [
                ["Hubungi 112", "tel:112"],
                ["Healing119 (119 lalu tekan 8)", "tel:119"],
                ["Bicara dengan konselor", "/dukungan.php#konselor"],
                ["Layanan bantuan", "/bantuan.php"],
            ],
        ];
    }

    $q = " " . safety_normalize($question) . " ";
    if (trim($q) === "") {
        return ["crisis" => false, "reply" => "Tulis pertanyaanmu, atau pilih salah satu pertanyaan di bawah.", "links" => []];
    }

    $best = null;
    $bestScore = 0;
    foreach (BOT_KB as $entry) {
        $score = 0;
        foreach ($entry["keys"] as $key) {
            if (strpos($q, safety_normalize($key)) !== false) {
                // frasa lebih panjang bernilai lebih tinggi
                $score += max(1, substr_count($key, " ") + 1);
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $entry;
        }
    }

    if ($best !== null) {
        return ["crisis" => false, "reply" => $best["a"], "links" => $best["links"]];
    }

    return [
        "crisis" => false,
        "reply"  => "Maaf, aku belum paham pertanyaan itu. Aku hanya bisa menjawab soal cara memakai MoodJourney. "
                  . "Kalau kamu butuh bantuan lain atau ingin bicara dengan seseorang, tim kami atau konselor bisa membantu.",
        "links"  => [["Chat dengan tim", "/chat.php"], ["Bicara dengan konselor", "/dukungan.php#konselor"]],
    ];
}

/** Daftar pertanyaan yang disarankan (chip). */
function bot_suggestions(): array
{
    $ids = ["catat", "password", "privasi", "musik", "chat", "hapusakun"];
    $out = [];
    foreach (BOT_KB as $e) {
        if (in_array($e["id"], $ids, true)) {
            $out[] = $e["q"];
        }
    }
    return $out;
}
