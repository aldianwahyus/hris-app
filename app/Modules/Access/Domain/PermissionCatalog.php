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
 *
 * Kategori di sini SENGAJA disamakan per-MODUL dengan pengelompokan
 * sidebar (resources/views/layouts/app.blade.php — Absensi/Cuti/Lembur/
 * SPPD/Izin/Tukar Shift/Payroll/LMS/Kepegawaian/Pengawasan/Admin
 * Sistem/Admin Sistem (IT)), BUKAN lagi dikelompokkan lintas-modul
 * ("Persetujuan"/"Pencairan/Pembayaran"/"SDM" gabungan) seperti
 * sebelumnya — supaya admin yang mengelola izin satu modul (mis. SPPD)
 * menemukan SEMUA permission modul itu (approval MAUPUN pencairan)
 * dalam satu kelompok yang sama, mencerminkan cara sidebar sekarang
 * mengelompokkan menu. Dua sistem ini tetap independen secara data
 * (kategori di sini murni label tampilan, tidak dibaca sidebar atau
 * sebaliknya) — hanya SELARAS agar tidak membingungkan admin yang
 * sudah terbiasa dengan pengelompokan sidebar.
 */
final readonly class PermissionCatalog
{
    /** @return array<string, array{0: string, 1: string}> slug => [label, kategori] */
    public static function all(): array
    {
        return [
            'overtime-approval.view' => ['Antrean Lembur — lihat', 'Lembur'],
            'overtime-approval.decide' => ['Antrean Lembur — putuskan', 'Lembur'],
            'overtime-disbursement.hc' => ['Pembayaran Lembur (Kantor Pusat)', 'Lembur'],
            'overtime-recap.view' => ['Rekap Biaya Lembur', 'Lembur'],
            'overtime-disbursement.branch' => ['Pembayaran Lembur (kantor sendiri)', 'Lembur'],
            'leave-approval.view' => ['Antrean Cuti — lihat', 'Cuti'],
            'leave-approval.decide' => ['Antrean Cuti — putuskan', 'Cuti'],
            'bekal-cuti-disbursement.hc' => ['Pencairan Bekal Cuti (bank-wide)', 'Cuti'],
            'bekal-cuti-disbursement.branch' => ['Pencairan Bekal Cuti (kantor sendiri)', 'Cuti'],
            'payroll-approval.manage' => ['Persetujuan Payroll (generate/setujui/tolak/buka kembali)', 'Payroll'],
            'payroll-deduction.manage' => ['Potongan Gaji', 'Payroll'],
            'sppd-approval.view' => ['Antrean SPPD — lihat', 'SPPD'],
            'sppd-approval.decide' => ['Antrean SPPD — putuskan', 'SPPD'],
            'sppd-disbursement.hc.view' => ['Pencairan SPPD (bank-wide) — lihat', 'SPPD'],
            'sppd-disbursement.hc.decide' => ['Pencairan SPPD (bank-wide) — cairkan', 'SPPD'],
            'sppd-disbursement.branch' => ['Pencairan SPPD (kantor sendiri)', 'SPPD'],
            'sppd-memo.manage' => ['SPPD Massal — input memo, cetak Surat Jalan & Rincian Lumpsum', 'SPPD'],
            'sppd-payment-batch.hc' => ['Pembayaran SPPD Massal (Kantor Pusat)', 'SPPD'],
            'sppd-payment-batch.branch' => ['Pembayaran SPPD Massal (kantor sendiri)', 'SPPD'],
            'izin-approval.view' => ['Antrean Izin Tidak Masuk Bekerja — lihat', 'Izin'],
            'izin-approval.decide' => ['Antrean Izin Tidak Masuk Bekerja — putuskan', 'Izin'],
            'shift-swap-approval.view' => ['Antrean Tukar Shift — lihat', 'Tukar Shift'],
            'shift-swap-approval.decide' => ['Antrean Tukar Shift — putuskan', 'Tukar Shift'],
            'outside-attendance-approval.view' => ['Antrean Absen Luar Kantor — lihat', 'Absensi'],
            'outside-attendance-approval.decide' => ['Antrean Absen Luar Kantor — putuskan', 'Absensi'],
            'attendance-recap.view' => ['Rekap Absensi', 'Absensi'],
            'employee-approval.manage' => ['Persetujuan Data Pegawai (profil + pegawai baru)', 'Kepegawaian'],
            'employee-directory.manage' => ['Data Pegawai (kantor sendiri)', 'Kepegawaian'],
            'employee-records.manage' => ['Riwayat Pegawai (keluarga/kerja/kesehatan)', 'Kepegawaian'],
            'decision-letter.manage' => ['Surat Keputusan (SK)', 'Kepegawaian'],
            'hc-dashboard.view' => ['Dasbor HC', 'Kepegawaian'],
            'branch-dashboard.view' => ['Dasbor Cabang (kantor sendiri)', 'Kepegawaian'],
            'employee-position-record.view' => ['Record Pegawai — riwayat posisi per bulan', 'Kepegawaian'],
            'lms-catalog.manage' => ['LMS — kelola katalog kursus, jadwal kelas, catat kelulusan/nilai', 'LMS'],
            'lms-enrollment-approval.view' => ['Antrean Pendaftaran Pelatihan — lihat', 'LMS'],
            'lms-enrollment-approval.decide' => ['Antrean Pendaftaran Pelatihan — putuskan', 'LMS'],
            'audit-log.view' => ['Log Audit', 'Pengawasan'],
            'org-chart.view' => ['Struktur Organisasi', 'Admin Sistem'],
            'sysadmin-content.manage' => ['Admin Sistem — konten bersama (kalender libur, pola shift, penugasan shift, formasi kantor, menu Aplikasi Mobile)', 'Admin Sistem'],
            'sysadmin-it.manage' => ['Admin Sistem — IT (parameter, skala gaji, tarif SPPD, geofence, absensi mesin, pegawai maker)', 'Admin Sistem (IT)'],
        ];
    }

    /** @return array<int, string> */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(self::all(), 1)));
    }
}
