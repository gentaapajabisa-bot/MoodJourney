<?php
/**
 * Fungsi pembantu formulir konselor. Dimuat langsung oleh halaman yang memakainya
 * (require_once), jadi tidak bergantung pada bootstrap.php.
 */

if (!function_exists("cform_topics")) {

    /** Topik yang bisa dipilih di formulir (kunci => label). */
    function cform_topics(): array
    {
        return [
            "stres"    => "Stres & kelelahan",
            "cemas"    => "Cemas & overthinking",
            "sedih"    => "Sedih & suasana hati rendah",
            "hubungan" => "Hubungan & pertemanan",
            "keluarga" => "Keluarga",
            "akademik" => "Sekolah / kuliah",
            "kerja"    => "Pekerjaan",
            "diri"     => "Mengenal & menerima diri",
            "lainnya"  => "Lainnya",
        ];
    }

    /** Konselor aktif yang menandai dirinya tersedia (untuk ditampilkan ke pengguna). */
    function cform_available(): array
    {
        $rows = db_all(
            "SELECT u.id, u.name, u.counselor_topics, u.counselor_hours
             FROM users u
             WHERE u.role = 'counselor' AND u.is_active = 1 AND u.counselor_available = 1
             ORDER BY u.name ASC"
        );
        foreach ($rows as &$r) {
            $r["topic_keys"] = array_values(array_filter(array_map("trim", explode(",", (string) $r["counselor_topics"]))));
        }
        unset($r);
        return $rows;
    }

    /**
     * Rapikan nomor telepon. Mengembalikan nomor bersih (mis. 081234567890 atau +6281234567890),
     * atau null kalau formatnya tidak masuk akal.
     */
    function cform_clean_phone(string $raw): ?string
    {
        $p = preg_replace('/[\s().\-]/', "", trim($raw));
        if ($p === null || !preg_match('/^\+?\d{9,15}$/', $p)) {
            return null;
        }
        if (!preg_match('/^(0|62|\+62)/', $p)) {
            return null; // hanya nomor Indonesia: 08.., 628.., +628..
        }
        return $p;
    }

    /** Nomor untuk tautan WhatsApp (tanpa +, diawali 62). */
    function cform_wa_number(string $phone): string
    {
        $d = ltrim($phone, "+");
        return $d[0] === "0" ? "62" . substr($d, 1) : $d;
    }
}
