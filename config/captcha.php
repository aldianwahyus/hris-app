<?php

return [
    'disable' => env('CAPTCHA_DISABLE', false),
    // HANYA huruf kapital + angka, dan HANYA yang tidak rawan tertukar
    // (I/O/1/0 dibuang — pasangan klasik yang paling sering salah baca).
    // Huruf kecil sengaja tidak diikutkan: pencocokan captcha ini tidak
    // peka besar/kecil, jadi mencampur kapital+kecil hanya menambah
    // kebingungan visual (mis. "Cc"/"Kk"/"Oo" nyaris tak beda di font
    // ini) tanpa menambah keamanan sama sekali.
    'characters' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 2, 3, 4, 5, 6, 7, 8, 9],
    // Dialihkan ke aset bawaan paket (bukan default publish
    // dirname(__DIR__).'/assets/...') — direktori itu tidak pernah ada di
    // root proyek ini, sehingga captcha gagal generate (mencari file
    // latar/font yang tidak ditemukan) sampai disadari saat uji manual.
    'fontsDirectory' => base_path('vendor/mews/captcha/assets/fonts'),
    'bgsDirectory' => base_path('vendor/mews/captcha/assets/backgrounds'),
    'default' => [
        'length' => 6,
        'width' => 345,
        'height' => 65,
        'quality' => 90,
        'math' => false,
        'expire' => 60,
        'encrypt' => false,
    ],
    // bgImage dimatikan dan lines dikurangi drastis (6 -> 2) — preset
    // bawaan menumpuk tekstur foto latar + 6 garis acak DI ATAS
    // karakter, ditambah kontras 0, hasilnya nyaris tidak terbaca
    // (dilaporkan pengguna). Ini captcha internal HRIS untuk memperlambat
    // bruteforce login, bukan captcha publik yang perlu tahan bot
    // canggih — keterbacaan pegawai jauh lebih penting di sini.
    'flat' => [
        'length' => 6,
        'fontColors' => ['#2c3e50', '#8e44ad', '#303f9f', '#795548'],
        'width' => 345,
        'height' => 65,
        'math' => false,
        'quality' => 100,
        'lines' => 2,
        'bgImage' => false,
        'bgColor' => '#F5F7F6',
        'contrast' => 8,
    ],
    'mini' => [
        'length' => 3,
        'width' => 60,
        'height' => 32,
    ],
    'inverse' => [
        'length' => 5,
        'width' => 120,
        'height' => 36,
        'quality' => 90,
        'sensitive' => true,
        'angle' => 12,
        'sharpen' => 10,
        'blur' => 2,
        'invert' => false,
        'contrast' => -5,
    ],
    'math' => [
        'length' => 9,
        'width' => 120,
        'height' => 36,
        'quality' => 90,
    ],
];
