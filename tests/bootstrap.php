<?php

declare(strict_types=1);

/**
 * Bug ditemukan lewat evaluasi PM/client (2026-08-31): phpunit.xml
 * <env force="true"> TIDAK BENAR-BENAR menimpa APP_ENV/DB_DATABASE dkk.
 * — docker-compose.yml membakukan nilai-nilai itu LANGSUNG ke environment
 * OS kontainer (APP_ENV=local, DB_DATABASE=hris), yang mengisi
 * $_ENV/$_SERVER saat proses PHP dimulai. putenv() milik PHPUnit hanya
 * mengubah tabel environ tingkat proses — TIDAK PERNAH menimpa
 * $_ENV/$_SERVER yang sudah terisi, dan Laravel (lewat vlucas/phpdotenv)
 * membaca $_ENV/$_SERVER lebih dulu. Akibatnya SELURUH test feature
 * yang memakai actingAs()->post(...) diam-diam gagal 419 (CSRF tidak
 * pernah bypass) ketika dijalankan sendiri/terfilter, dan seluruh suite
 * diam-diam berjalan di atas database hris (BUKAN hris_testing) — bukan
 * cuma tidak terisolasi, DB pengembangan bisa ikut termodifikasi test.
 *
 * Perbaikan: paksa $_ENV/$_SERVER (bukan cuma putenv) SEBELUM
 * vendor/autoload.php (dan karenanya Dotenv) sempat membaca environment
 * apa pun.
 */
$overrides = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'hris_testing',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
];

foreach ($overrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
