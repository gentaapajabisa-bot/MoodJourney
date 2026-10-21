<?php
/**
 * Pembungkus halaman Dukungan.
 * Menjalankan support.php apa adanya, lalu:
 *  - menyembunyikan bagian lama "Bicara dengan konselor" (#konselor), dan
 *  - menampilkan kartu yang mengarah ke formulir konselor.
 * Fitur lain di halaman Dukungan (chat tim, ketenangan, dsb.) tidak berubah.
 */
ob_start(function (string $html): string {
    if (stripos($html, "<main") === false) {
        return $html;
    }

    $cta = '<div class="container narrow"><section class="card cform-cta" id="formulir-konselor">'
        . '<h2>Bicara dengan konselor</h2>'
        . '<p>Isi formulir singkat (sekitar 2 menit). Admin akan memeriksa ketersediaan konselor lalu mengarahkanmu ke konselor yang sesuai.</p>'
        . '<a class="btn" href="/formulir-konselor.php">Isi formulir konselor</a>'
        . '</section></div>';

    $html = preg_replace('/(<main\b[^>]*>)/i', '$1' . $cta, $html, 1) ?? $html;

    $css = '<style>#konselor{display:none!important}</style>';
    $pos = stripos($html, "</head>");
    if ($pos !== false) {
        $html = substr($html, 0, $pos) . $css . substr($html, $pos);
    }
    return $html;
});

require __DIR__ . "/support.php";
