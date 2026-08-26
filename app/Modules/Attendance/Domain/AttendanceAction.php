<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

/**
 * Jenis aksi absen GPS — klien (ESS web/mobile) MENYATAKAN maksudnya
 * secara eksplisit (tombol yang ditekan pengguna), BUKAN disimpulkan
 * dari urutan scan seperti model 2-tahap lama (masuk/pulang). Perlu
 * eksplisit sejak Istirahat ditambahkan: begitu sudah Masuk, ADA DUA
 * aksi lanjutan yang sah sekaligus (Istirahat ATAU langsung Pulang —
 * istirahat opsional), jadi urutan scan saja tidak lagi cukup menyimpulkan
 * maksud pengguna — lihat RecordGpsAttendance.
 */
enum AttendanceAction: string
{
    case CheckIn = 'masuk';
    case BreakStart = 'istirahat';
    case BreakEnd = 'kembali';
    case CheckOut = 'pulang';
}
