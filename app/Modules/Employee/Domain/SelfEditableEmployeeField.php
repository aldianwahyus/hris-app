<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

/**
 * Field emp_employees yang boleh diubah pegawai SENDIRI, TANPA
 * persetujuan — "CV Saya" (ESS). Sengaja whitelist TERPISAH dari
 * `EditableEmployeeField` (itu untuk hr_admin/SYSADMIN, data
 * ORGANISASI lewat maker-checker): daftar di sini HANYA data pribadi
 * murni, risiko rendah, bukan proses bisnis SDM — TIDAK PERNAH memuat
 * position_id/office_id/person_grade/nrp/status kepegawaian/dll.
 */
enum SelfEditableEmployeeField: string
{
    case Alamat = 'alamat';
    case NoTelepon = 'no_telepon';
    case KontakDaruratNama = 'kontak_darurat_nama';
    case KontakDaruratHubungan = 'kontak_darurat_hubungan';
    case KontakDaruratTelepon = 'kontak_darurat_telepon';
    case PendidikanTerakhir = 'pendidikan_terakhir';
    case PendidikanJurusan = 'pendidikan_jurusan';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $f) => $f->value, self::cases());
    }
}
