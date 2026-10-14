<?php
/** ZIP sederhana (tanpa kompresi, tanpa ekstensi zip) untuk unduhan cadangan. */

/**
 * @param array<string,string> $files nama berkas => isi
 */
function zip_build(array $files): string
{
    $out = "";
    $central = "";
    $count = 0;
    $dosTime = ((int) date("H") << 11) | ((int) date("i") << 5) | (int) ((int) date("s") / 2);
    $dosDate = (((int) date("Y") - 1980) << 9) | ((int) date("n") << 5) | (int) date("j");

    foreach ($files as $name => $data) {
        $name = (string) $name;
        $crc = hexdec(hash("crc32b", $data));
        $size = strlen($data);
        $offset = strlen($out);
        $nameLen = strlen($name);

        // flag bit 11 = nama berkas UTF-8
        $local = pack("VvvvvvVVVvv", 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $name;
        $out .= $local . $data;

        $central .= pack("VvvvvvvVVVvvvvvVV", 0x02014b50, 20, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
        $count++;
    }

    $end = pack("VvvvvVVv", 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($out), 0);
    return $out . $central . $end;
}
