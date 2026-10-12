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
        "a" => "Streak adalah jumlah hari berturut-turut kamu mencatat mood. Selain itu ada \"Perjalanan kupu-kupu\" yang lebih ramah: satu hari terlewat per minggu dimaafkan, dan kalau terlewat lagi, perjalananmu hanya turun separuh, bukan kembali ke nol. Tidak perlu dikejar sampai tertekan. Mencatat sesekali pun tetap berguna.",
        "links" => [["Lihat metamorfosis", "/metamorfosis.php"]],
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
        "keys" => ["foto", "gambar", "upload", "unggah", "lampiran", "picture", "avatar", "foto profil"],
        "a" => "Di form catatan ada bagian \"Foto\". Pilih satu atau beberapa foto (maksimal 4 per catatan), dan ukurannya dikecilkan otomatis. Untuk membuang foto lama, buka catatannya lewat tombol Ubah lalu centang \"Hapus\". Foto hanya bisa dilihat olehmu. Foto profil diatur di halaman Profil.",
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
    [
        "id" => "panduan",
        "q" => "Bagaimana cara memakai MoodJourney dari awal?",
        "keys" => ["panduan", "tata cara", "cara pakai", "cara menggunakan", "cara memakai", "mulai dari mana", "tutorial", "langkah langkah", "langkah-langkah", "fitur apa saja", "cara kerja"],
        "a" => "Langkah singkat memakai MoodJourney:\n1. Daftar dan masuk ke akunmu.\n2. Klik \"Catat mood\", pilih emoji, geser kekuatan perasaan, tandai aktivitas, lalu simpan.\n3. Lihat ringkasannya di Dashboard.\n4. Buka Riwayat untuk mencari atau mengunduh catatan.\n5. Buka Wawasan untuk statistik, target, pencapaian, dan metamorfosis kupu-kupu.\n6. Butuh jeda? Coba ruang Tenang. Butuh bicara? Buka Dukungan.\nTanya saja kalau mau penjelasan detail tiap langkah.",
        "links" => [["Catat mood", "/diary.php"], ["Wawasan", "/statistics.php"], ["Ruang tenang", "/tenang.php"]],
    ],
    [
        "id" => "daftar",
        "q" => "Bagaimana cara daftar dan masuk?",
        "keys" => ["cara daftar", "daftar akun", "buat akun", "cara login", "cara masuk", "registrasi", "register", "sign up"],
        "a" => "Cara daftar:\n1. Buka halaman Daftar.\n2. Isi nama, email, dan password.\n3. Klik Daftar.\nSetelah itu masuk lewat halaman Masuk dengan email dan password yang sama. Kalau lupa password, klik \"Lupa password?\" di halaman Masuk.",
        "links" => [["Daftar", "/register.php"], ["Masuk", "/login.php"]],
    ],
    [
        "id" => "riwayat",
        "q" => "Bagaimana mencari atau memfilter catatan lama?",
        "keys" => ["riwayat", "filter", "cari catatan", "pencarian", "catatan lama", "cari mood"],
        "a" => "Cara mencari catatan lama:\n1. Buka menu Riwayat.\n2. Ketik kata di kolom Cari, atau pilih Mood, Aktivitas, dan rentang tanggal.\n3. Klik \"Terapkan filter\".\nDi tiap catatan ada tombol Ubah dan Hapus. Tombol Unduh CSV dan Cetak / PDF mengikuti filter yang sedang aktif.",
        "links" => [["Buka riwayat", "/history.php"]],
    ],
    [
        "id" => "metamorfosis",
        "q" => "Apa itu metamorfosis kupu-kupu?",
        "keys" => ["kupu", "metamorfosis", "kepompong", "ulat", "telur", "sayap", "perjalananmu"],
        "a" => "Metamorfosis adalah streak versi lembut. Setiap hari yang kamu catat menambah satu hari perjalanan: Telur (0-2 hari), Ulat (3-6), Kepompong (7-13), Kupu-kupu muda (14-29), lalu Kupu-kupu dewasa (30+). Satu hari terlewat per minggu dimaafkan. Kalau terlewat lagi, perjalanan turun separuh dan kupu-kupu beristirahat di kepompong sampai kamu mencatat lagi. Warna sayapnya mengikuti mood yang paling sering kamu catat 14 hari terakhir.",
        "links" => [["Lihat metamorfosis", "/metamorfosis.php"]],
    ],
    [
        "id" => "setahun",
        "q" => "Apa itu kalender setahun?",
        "keys" => ["setahun", "year in pixels", "piksel", "kalender tahunan", "kalender setahun", "satu tahun"],
        "a" => "Kalender setahun menampilkan satu kotak berwarna untuk setiap hari. Buka Wawasan → Setahun. Warnanya bisa diganti antara Skor dan Mood. Arahkan kursor atau sentuh sebuah kotak untuk melihat harinya, dan klik \"Unduh gambar\" untuk menyimpannya sebagai gambar (hanya warna, tanpa isi catatan).",
        "links" => [["Buka setahun", "/setahun.php"]],
    ],
    [
        "id" => "pola",
        "q" => "Apa itu tab Pola?",
        "keys" => ["pola", "kecenderungan", "wawasan pola", "aktivitas yang bikin", "hari terbaik", "hari terberat", "apa yang membuat moodku"],
        "a" => "Tab Pola (di Wawasan) menunjukkan kecenderungan dari catatanmu: aktivitas yang biasanya menyertai hari yang lebih baik atau berat, hari terbaik dan terberat dalam seminggu, serta tren 30 hari. Muncul setelah sekitar 14 hari tercatat dan dihitung dari angka saja, tanpa membaca ceritamu. Ini hanya hubungan yang terlihat, bukan sebab-akibat.",
        "links" => [["Buka Pola", "/pola.php"]],
    ],
    [
        "id" => "bagikan",
        "q" => "Bagaimana membagikan perjalanan kupu-kupuku?",
        "keys" => ["bagikan", "share", "kartu", "story", "instagram", "whatsapp", "pamer", "kirim gambar"],
        "a" => "Buka Metamorfosis, lalu klik \"Bagikan perjalananku\". Pilih warna latar, tentukan apakah nama depan dan statistik ikut ditampilkan, lalu klik Bagikan atau Unduh gambar. Isi catatan, mood, dan fotomu tidak pernah ikut di gambar.",
        "links" => [["Bagikan perjalanan", "/bagikan.php"]],
    ],
    [
        "id" => "ringkasan",
        "q" => "Apa itu ringkasan mingguan?",
        "keys" => ["ringkasan", "mingguan", "sorotan", "rekap"],
        "a" => "Ringkasan mingguan ada di Wawasan → Ringkasan mingguan. Isinya rangkuman hari demi hari, sorotan minggu itu, aktivitas yang sering muncul, dan progres targetmu. Minggu dihitung dari Senin sampai Minggu.",
        "links" => [["Buka ringkasan", "/ringkasan.php"]],
    ],
    [
        "id" => "tentang",
        "q" => "Apa itu MoodJourney?",
        "keys" => ["apa itu moodjourney", "kegunaan", "fungsi website", "tentang moodjourney", "manfaat", "untuk apa", "apa ini", "halo", "hai", "hi", "selamat pagi", "selamat siang", "selamat malam", "permisi"],
        "a" => "Halo! MoodJourney adalah buku harian mood: kamu mencatat perasaan setiap hari, lalu melihat polanya lewat grafik, kalender setahun, dan kupu-kupu yang tumbuh seiring kebiasaanmu. Ini bukan layanan medis dan tidak mendiagnosis apa pun. Mau mulai? Tanya \"cara memakai dari awal\" atau klik tombol di bawah.",
        "links" => [["Catat mood", "/diary.php"], ["Dashboard", "/dashboard.php"]],
    ],
    [
        "id" => "siapalihat",
        "q" => "Siapa yang bisa membaca catatanku?",
        "keys" => ["siapa yang bisa baca", "siapa bisa lihat", "dibaca admin", "admin bisa lihat", "admin baca", "baca diary", "lihat diary", "isi diary", "baca catatan", "lihat catatan", "membaca catatan", "mengintip", "catatanku aman", "rahasia", "dilihat orang"],
        "a" => "Isi catatan, cerita, dan fotomu hanya terlihat olehmu. Admin hanya melihat data akun dan angka ringkas (misalnya jumlah catatan), bukan isi diary. Percakapan di chat dengan tim memang bisa dibaca admin atau konselor yang menanganinya.",
        "links" => [["Kebijakan privasi", "/privasi.php"]],
    ],
    [
        "id" => "install",
        "q" => "Bisa dipasang seperti aplikasi di HP?",
        "keys" => ["install", "instal", "pasang aplikasi", "add to home", "layar utama", "offline", "pwa", "aplikasi hp", "download aplikasi", "unduh aplikasi"],
        "a" => "Bisa. Di Chrome Android, buka menu ⋮ lalu pilih \"Tambahkan ke layar utama\" atau \"Instal aplikasi\". Di iPhone (Safari), ketuk tombol Bagikan lalu \"Tambah ke Layar Utama\". Setelah itu MoodJourney terbuka seperti aplikasi biasa.",
        "links" => [],
    ],
    [
        "id" => "keluar",
        "q" => "Bagaimana cara keluar (logout)?",
        "keys" => ["logout", "log out", "keluar akun", "cara keluar", "sign out", "keluar dari akun"],
        "a" => "Buka menu Profil, lalu klik tombol Keluar di bagian bawah halaman. Di HP, buka dulu menu ☰ di pojok kanan atas.",
        "links" => [["Buka profil", "/profile.php"]],
    ],
    [
        "id" => "ganti_profil",
        "q" => "Bagaimana mengganti nama atau email?",
        "keys" => ["ganti nama", "ubah nama", "ganti email", "ubah email", "edit profil", "ubah profil", "data akun", "foto profil", "ganti foto profil", "hapus foto profil", "pasang foto profil", "avatar", "foto akun"],
        "a" => "Buka Profil. Di sana kamu bisa mengubah nama, email, dan password. Untuk foto profil, klik \"Pilih foto\", atur potongannya, lalu simpan. Kamu juga bisa menghapusnya dengan tombol \"Hapus foto\".",
        "links" => [["Buka profil", "/profile.php"]],
    ],
    [
        "id" => "mood_kuat",
        "q" => "Apa arti geser kekuatan perasaan?",
        "keys" => ["kekuatan", "intensitas", "geser", "skala 1", "slider", "angka 1 sampai 5", "seberapa kuat"],
        "a" => "Setelah memilih emoji, geser penggeser dari 1 (sangat ringan) sampai 5 (sangat kuat). Misalnya Sedih dengan kekuatan 5 berarti sedihnya terasa sangat dalam. Nilai ini dipakai untuk menghitung grafik dan skor harianmu.",
        "links" => [["Catat mood", "/diary.php"]],
    ],
    [
        "id" => "tenang",
        "q" => "Apa itu ruang Tenang?",
        "keys" => ["tenang", "napas", "bernapas", "meditasi", "relaks", "menenangkan", "grounding"],
        "a" => "Tenang adalah halaman latihan singkat: pernapasan, grounding, dan musik yang menenangkan. Cocok dipakai kapan pun terasa penuh. Ini bukan pengganti bantuan profesional.",
        "links" => [["Buka Tenang", "/tenang.php"]],
    ],
    [
        "id" => "konselor",
        "q" => "Bagaimana bicara dengan konselor?",
        "keys" => ["konselor", "psikolog", "curhat", "bicara dengan", "ngobrol dengan", "butuh teman", "butuh bantuan"],
        "a" => "Buka Dukungan, lalu pilih \"Bicara dengan konselor\" dan tulis topiknya. Permintaanmu masuk antrean dan akan diambil konselor atau ditugaskan admin. Kalau kondisinya darurat, hubungi 112 atau Healing119 (119 lalu tekan 8).",
        "links" => [["Bicara dengan konselor", "/dukungan.php#konselor"], ["Layanan bantuan", "/bantuan.php"]],
    ],
    [
        "id" => "error",
        "q" => "Halaman tidak mau terbuka atau error",
        "keys" => ["error", "eror", "rusak", "tidak mau terbuka", "macet", "loading terus", "blank", "putih", "gagal", "bug", "tidak muncul"],
        "a" => "Coba muat ulang halaman (di HP, tarik ke bawah). Kalau tampilannya tidak berubah setelah pembaruan, tekan Ctrl+Shift+R di komputer atau hapus data situs di browser HP. Kalau masih bermasalah, kirim pesan lewat chat dengan tim sambil menyebut halaman dan langkah yang kamu lakukan.",
        "links" => [["Chat dengan tim", "/chat.php"]],
    ],
    [
        "id" => "biaya",
        "q" => "Apakah MoodJourney gratis?",
        "keys" => ["gratis", "biaya", "bayar", "berbayar", "harga", "premium"],
        "a" => "MoodJourney gratis dipakai. Tidak ada fitur yang perlu dibayar.",
        "links" => [],
    ],
];

/** Jawaban bot untuk satu pertanyaan. */
function bot_answer(string $question, ?int $userId = null): array
{
    try {
        return bot_answer_inner($question, $userId);
    } catch (Throwable $e) {
        error_log("[MoodJourney] bot error: " . $e->getMessage());
        return [
            "crisis" => false,
            "ai"     => false,
            "reply"  => "Maaf, ada gangguan kecil. Coba tanyakan lagi sebentar lagi, atau kirim pesan lewat chat dengan tim.",
            "links"  => [["Chat dengan tim", "/chat.php"]],
        ];
    }
}

function bot_answer_inner(string $question, ?int $userId = null): array
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
        return ["crisis" => false, "reply" => "Tulis pertanyaanmu, atau pilih salah satu pertanyaan di bawah.", "links" => [], "suggest" => bot_suggestions()];
    }

    // 1. kata kunci (frasa lebih panjang bernilai lebih tinggi)
    $best = null;
    $bestScore = 0;
    foreach (BOT_KB as $entry) {
        $score = 0;
        foreach ($entry["keys"] as $key) {
            if (strpos($q, " " . safety_normalize($key) . " ") !== false || strpos($q, safety_normalize($key)) !== false) {
                $score += max(1, substr_count($key, " ") + 1);
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $entry;
        }
    }

    // 2. cocokkan kata penting (sudah diringkas ke bentuk dasar) kalau kata kunci belum cocok
    $fuzzy = null;
    if ($best === null) {
        [$fuzzy, $fuzzyScore] = bot_fuzzy_match($q);
        if ($fuzzy !== null && $fuzzyScore >= 2) {
            return bot_faq_reply($fuzzy);
        }
        $fuzzy = null; // kecocokan lemah tidak dipakai
    }

    $words = count(array_filter(explode(" ", trim($q))));
    $confident = $best !== null && ($bestScore >= 2 || $words <= 6);
    if ($confident) {
        return bot_faq_reply($best);
    }

    $candidate = $best ?? $fuzzy;

    // 3. AI untuk pertanyaan lain (kalau aktif); FAQ tetap jadi cadangan
    if ($userId !== null && ai_enabled()) {
        $ai = ai_answer($question, $userId);
        if ($ai !== null && !empty($ai["ai"])) {
            return $ai;
        }
        if ($candidate !== null) {
            return bot_faq_reply($candidate);
        }
        if ($ai !== null) {
            return $ai; // pesan batas harian
        }
    } elseif ($candidate !== null) {
        return bot_faq_reply($candidate);
    }

    // 4. tidak ada yang cocok: beri arah, bukan jalan buntu
    return [
        "crisis"  => false,
        "ai"      => false,
        "reply"   => "Aku belum menemukan jawaban yang pas untuk itu, tapi aku bisa bantu soal cara memakai MoodJourney: mencatat mood, foto, riwayat, target, kupu-kupu, kalender setahun, dan akun. "
                   . "Coba tulis dengan kata lain, atau pilih salah satu pertanyaan di bawah. Kalau butuh bantuan langsung, tim kami atau konselor siap membantu.",
        "links"   => [["Chat dengan tim", "/chat.php"], ["Bicara dengan konselor", "/dukungan.php#konselor"]],
        "suggest" => bot_suggestions(),
    ];
}

function bot_faq_reply(array $entry): array
{
    return ["crisis" => false, "ai" => false, "reply" => $entry["a"], "links" => $entry["links"]];
}

/** Daftar pertanyaan yang disarankan (chip). */
function bot_suggestions(): array
{
    $ids = ["panduan", "catat", "metamorfosis", "riwayat", "privasi", "chat"];
    $out = [];
    foreach (BOT_KB as $e) {
        if (in_array($e["id"], $ids, true)) {
            $out[] = $e["q"];
        }
    }
    return $out;
}

/** Ringkas kata Indonesia ke bentuk dasar (kasar, cukup untuk mencocokkan pertanyaan). */
function bot_stem(string $w): string
{
    $w = (string) preg_replace('/(kan|an|i|lah|kah|nya)$/u', "", $w);
    $w = (string) preg_replace('/^(meng|meny|men|mem|me|peng|pen|pem|pe|ber|ter|di|ke|se)/u', "", $w);
    return $w;
}

const BOT_STOP = ["yang", "untuk", "dengan", "bagaimana", "gimana", "gmn", "apakah", "apa", "cara", "bisa", "dari", "saya", "aku", "mau", "ingin", "tolong", "dong", "kah", "itu", "ini", "moodjourney", "aplikasi", "website", "situs", "web", "kok", "tidak", "bukan", "ada", "atau", "dan", "pada", "dalam", "kenapa", "mengapa", "kapan", "dimana", "sih", "nih", "kalau", "jika", "sudah", "belum", "lagi", "akan"];

/**
 * Cadangan: cocokkan kata penting pertanyaan ke pertanyaan, kata kunci, dan isi jawaban tiap FAQ.
 * @return array{0: ?array, 1: int} [entri, skor]
 */
function bot_fuzzy_match(string $normalized): array
{
    $words = [];
    foreach (explode(" ", trim($normalized)) as $w) {
        if (mb_strlen($w) >= 3 && !in_array($w, BOT_STOP, true)) {
            $words[bot_stem($w)] = true;
        }
    }
    $words = array_filter(array_keys($words), fn($w) => mb_strlen($w) >= 3);
    if (!$words) {
        return [null, 0];
    }
    $best = null;
    $bestScore = 0;
    foreach (BOT_KB as $entry) {
        $core = " " . implode(" ", array_map("bot_stem", explode(" ", safety_normalize($entry["q"] . " " . implode(" ", $entry["keys"]))))) . " ";
        $body = " " . implode(" ", array_map("bot_stem", explode(" ", safety_normalize($entry["a"])))) . " ";
        $score = 0;
        foreach ($words as $w) {
            if (strpos($core, $w) !== false) {
                $score += 2;
            } elseif (strpos($body, " " . $w) !== false) {
                $score += 1;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $entry;
        }
    }
    return $bestScore >= 1 ? [$best, $bestScore] : [null, 0];
}
