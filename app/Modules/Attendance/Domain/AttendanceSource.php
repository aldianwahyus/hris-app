<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain;

/** Asal pencatatan kehadiran — memengaruhi validasi yang berlaku (geofence hanya untuk GPS). */
enum AttendanceSource: string
{
    case Gps = 'gps';
    case Fingerprint = 'fingerprint';

    // Override manual saat pengajuan absen luar kantor disetujui Pimpinan
    // Kantor — TIDAK melalui GeofencePolicy sama sekali (lihat
    // DecideOutsideAttendanceRequest), lat/lng selalu null untuk sumber ini.
    case LuarKantor = 'luar_kantor';
}
