<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/**
 * Katalog label+kategori untuk permission dinamis (basis 30 dari migrasi
 * 2026_08_28_000001_seed_dynamic_permissions.php, +3 dari modul LMS di
 * 2026_08_29_000004_seed_lms_permissions.php, dst. tiap modul baru
 * menambah barisnya sendiri di sini) — MURNI untuk
 * tampilan halaman "Peta Peran" yang sekarang bisa diedit, BUKAN
 * otorisasi. Keputusan akses sesungguhnya 100% di tabel
 * role_has_permissions (baca live lewat RoleFeatureMapController) —
 * kelas ini cuma memetakan slug ke label manusiawi, pola sama
 * SkType::label()/DeductionType::label() (enum pemetaan label, bukan
 * penentu keputusan).
 *
 * MENGGANTIKAN RoleFeatureMap (DIHAPUS) yang dulu memetakan Role→daftar
 * fitur secara statis — sekarang arahnya terbalik (permission→label)
 * karena keputusan "role mana punya izin apa" sudah pindah ke data.
 */
final readonly class PermissionCatalog
{
    /** @return array<string, array{0: string, 1: string}> slug => [label, kategori] */
    public static function all(): array
    {
        return [
            'overtime-approval.view' => ['Antrean Lembur — lihat', 'Persetujuan'],
            'overtime-approval.decide' => ['Antrean Lembur — putuskan', 'Persetujuan'],
            'overtime-disbursement.hc' => ['Pembayaran Lembur (Kantor Pusat)', 'Pencairan/Pembayaran'],
            'leave-approval.view' => ['Antrean Cuti — lihat', 'Persetujuan'],
            'leave-approval.decide' => ['Antrean Cuti — putuskan', 'Persetujuan'],
            'bekal-cuti-disbursement.hc' => ['Pencairan Bekal Cuti (bank-wide)', 'Pencairan/Pembayaran'],
            'payroll-approval.manage' => ['Persetujuan Payroll (generate/setujui/tolak/buka kembali)', 'Persetujuan'],
            'sppd-approval.view' => ['Antrean SPPD — lihat', 'Persetujuan'],
            'sppd-approval.decide' => ['Antrean SPPD — putuskan', 'Persetujuan'],
            'shift-swap-approval.view' => ['Antrean Tukar Shift — lihat', 'Persetujuan'],
            'shift-swap-approval.decide' => ['Antrean Tukar Shift — putuskan', 'Persetujuan'],
            'outside-attendance-approval.view' => ['Antrean Absen Luar Kantor — lihat', 'Persetujuan'],
            'outside-attendance-approval.decide' => ['Antrean Absen Luar Kantor — putuskan', 'Persetujuan'],
            'sppd-disbursement.hc.view' => ['Pencairan SPPD (bank-wide) — lihat', 'Pencairan/Pembayaran'],
            'sppd-disbursement.hc.decide' => ['Pencairan SPPD (bank-wide) — cairkan', 'Pencairan/Pembayaran'],
            'employee-approval.manage' => ['Persetujuan Data Pegawai (profil + pegawai baru)', 'Persetujuan'],
            'employee-directory.manage' => ['Data Pegawai (kantor sendiri)', 'SDM'],
            'employee-records.manage' => ['Riwayat Pegawai (keluarga/kerja/kesehatan)', 'SDM'],
            'decision-letter.manage' => ['Surat Keputusan (SK)', 'SDM'],
            'attendance-recap.view' => ['Rekap Absensi', 'SDM'],
            'payroll-deduction.manage' => ['Potongan Gaji', 'SDM'],
            'overtime-recap.view' => ['Rekap Biaya Lembur', 'SDM'],
            'overtime-disbursement.branch' => ['Pembayaran Lembur (kantor sendiri)', 'Pencairan/Pembayaran'],
            'bekal-cuti-disbursement.branch' => ['Pencairan Bekal Cuti (kantor sendiri)', 'Pencairan/Pembayaran'],
            'sppd-disbursement.branch' => ['Pencairan SPPD (kantor sendiri)', 'Pencairan/Pembayaran'],
            'audit-log.view' => ['Log Audit', 'Pengawasan'],
            'hc-dashboard.view' => ['Dasbor HC', 'Dasbor'],
            'org-chart.view' => ['Struktur Organisasi', 'Referensi'],
            'sysadmin-it.manage' => ['Admin Sistem — IT (parameter, skala gaji, tarif SPPD, geofence, absensi mesin, pegawai maker)', 'Admin Sistem'],
            'sysadmin-content.manage' => ['Admin Sistem — konten bersama (kalender libur, pola shift, penugasan shift, formasi kantor)', 'Admin Sistem'],
            'lms-catalog.manage' => ['LMS — kelola katalog kursus, jadwal kelas, catat kelulusan/nilai', 'SDM'],
            'lms-enrollment-approval.view' => ['Antrean Pendaftaran Pelatihan — lihat', 'Persetujuan'],
            'lms-enrollment-approval.decide' => ['Antrean Pendaftaran Pelatihan — putuskan', 'Persetujuan'],
            'izin-approval.view' => ['Antrean Izin Tidak Masuk Bekerja — lihat', 'Persetujuan'],
            'izin-approval.decide' => ['Antrean Izin Tidak Masuk Bekerja — putuskan', 'Persetujuan'],
        ];
    }

    /** @return array<int, string> */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(self::all(), 1)));
    }
}
