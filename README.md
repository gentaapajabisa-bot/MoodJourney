# MoodJourney (versi Vercel) — upgrade

Mood diary berbasis PHP + MySQL/TiDB yang berjalan di Vercel.

## Yang baru
- **Diary**: ubah dan hapus catatan, intensitas perasaan (1–5), tag aktivitas, 8 pilihan mood
- **Riwayat**: pencarian, filter mood/aktivitas/tanggal, pagination, unduh **CSV**, cetak/simpan **PDF**
- **Statistik**: grafik tren, kalender bulanan, rata-rata per hari dalam seminggu, hubungan aktivitas ↔ mood
- **Dashboard**: streak harian, pengingat, ringkasan 7 hari, kartu dukungan yang lembut bila beberapa hari terakhir terasa berat
- **Akun**: profil, ubah password, lupa/reset password, hapus akun
- **Chat**: pesan baru muncul otomatis, badge belum dibaca, status percakapan (terbuka/selesai)
- **Admin**: statistik gabungan (anonim), kelola pengguna (nonaktifkan, hapus, tautan reset), peringatan password bawaan
- **Keamanan**: proteksi CSRF, pembatasan percobaan login, escape output, header keamanan, password minimal 8 karakter
- **Lainnya**: mode gelap, halaman Bantuan (layanan darurat) dan Privasi, zona waktu WIB

## Wawasan, target, dan pengingat
- **Pencapaian**: 9 badge (jumlah catatan, streak, variasi aktivitas, target tercapai) dengan kartu yang bisa dibalik. Dihitung otomatis, termasuk dari data lama.
- **Ringkasan mingguan**: rangkuman Senin–Minggu, perbandingan dengan minggu sebelumnya, hari terbaik/terberat, aktivitas teratas. Bisa dilihat per minggu.
- **Target kebiasaan**: misalnya "olahraga 3 hari seminggu", lengkap dengan progres dan riwayat 4 minggu.
- **Pertanyaan refleksi** di form catatan, menyesuaikan mood yang dipilih.
- **PWA**: bisa dipasang ke layar utama, halaman offline, dan ikon. Halaman berisi data pribadi tidak pernah disimpan di cache.
- **Pengingat**:
  - *Browser*: notifikasi pada jam pilihan, **hanya saat MoodJourney terbuka** (tab boleh di latar belakang). Notifikasi push saat aplikasi tertutup tidak didukung oleh arsitektur ini.
  - *Email*: setiap hari pukul 20.00 WIB lewat Vercel Cron untuk pengguna yang mengaktifkannya dan belum mencatat. Perlu `RESEND_API_KEY`, `MAIL_FROM`, `APP_URL`, dan `CRON_SECRET`.
- Setelah deploy ulang yang mengubah `public/`, naikkan `VERSION` di `public/sw.js` supaya cache lama diganti.

## Efek 3D
- **Ringan (tanpa library)**: kartu miring mengikuti kursor, emoji berputar saat mood dipilih, kalender dan ringkasan 7 hari menonjol saat disorot, transisi halus antarhalaman.
- **Three.js (r128)**: planet mood dengan emoji yang mengorbit di halaman depan, dan "lanskap mood" 3D di halaman Statistik (tombol *Tampilkan 3D*, bisa diseret).
- Three.js dimuat dari cdnjs hanya saat dibutuhkan, tidak dijalankan di perangkat lemah (RAM/CPU kecil), mode hemat data, atau tanpa WebGL, dan selalu ada tampilan cadangan.
- Pengguna yang mengaktifkan "kurangi gerakan" tidak mendapat animasi otomatis.
- Mau tanpa CDN? Unduh `three.min.js` r128, simpan di `public/assets/js/`, lalu ubah `THREE_URL` di `public/assets/js/app.js` menjadi `/assets/js/three.min.js`.

## Struktur
```
api/index.php   satu-satunya fungsi serverless (router)
lib/pages/      semua halaman
lib/            bootstrap, database, sesi, auth, helper, grafik, migrasi
public/         CSS dan JS
db/             database.sql (opsional, lihat di bawah)
```
Router tunggal dipakai karena paket Vercel Hobby dibatasi 12 fungsi per deployment.
URL lama (`/login.php`, `/dashboard.php`, `/admin/index.php`, ...) tetap berlaku.

## Cara upgrade dari versi sebelumnya
1. **Ganti seluruh isi repo GitHub** dengan isi folder ini (hapus folder `api/` lama, karena sekarang hanya ada `api/index.php`), lalu push.
2. Vercel otomatis deploy ulang.
3. Buka situs. **Skema database di-upgrade otomatis** saat permintaan pertama: tabel dan kolom baru ditambahkan, data lama aman. Tidak perlu menjalankan SQL manual.
4. Login sebagai admin dan **ganti password bawaan** (`admin123`) di halaman Profil.

## Environment Variables
Lihat `.env.example`. Yang wajib: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_SSL=1`.

Opsional:
- `APP_TZ` zona waktu (default `Asia/Jakarta`)
- `APP_URL` alamat situs, untuk tautan di email reset
- `RESEND_API_KEY` + `MAIL_FROM` untuk mengirim email reset password otomatis lewat Resend.
  Tanpa ini, permintaan "lupa password" masuk ke panel admin, dan admin membuat tautan reset untuk dikirim ke pengguna.
- `APP_DEBUG=1` menampilkan pesan error lengkap (hanya untuk debugging)

## Catatan
- Skema tidak memakai FOREIGN KEY (kompatibel dengan TiDB); penghapusan data terkait dilakukan oleh aplikasi.
- Nomor layanan di halaman Bantuan (Healing119.id: 119 ext 8, Halo Kemenkes 1500-567, darurat 112) bisa berubah. Periksa berkala.
- Aplikasi ini bukan alat diagnosis maupun pengganti layanan kesehatan jiwa.
