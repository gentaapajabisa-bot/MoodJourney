<?php
/**
 * Cadangan sederhana kalau ekstensi mbstring tidak tersedia di runtime.
 * (Kalau tersedia, fungsi aslinya yang dipakai.)
 */

if (!defined("MB_CASE_TITLE")) {
    define("MB_CASE_TITLE", 2);
}

if (!function_exists("mb_internal_encoding")) {
    function mb_internal_encoding(?string $encoding = null)
    {
        return true;
    }
}

if (!function_exists("mb_strlen")) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        return (int) preg_match_all('/./us', $string);
    }
}

if (!function_exists("mb_substr")) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode("", array_slice($chars, $start, $length));
    }
}

if (!function_exists("mb_strtolower")) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        return strtolower($string);
    }
}

if (!function_exists("mb_convert_case")) {
    function mb_convert_case(string $string, int $mode, ?string $encoding = null): string
    {
        return ucwords(strtolower($string));
    }
}
