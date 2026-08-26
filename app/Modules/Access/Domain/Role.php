<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

/**
 * Peran (ARCH-001 §6.2). Sengaja TIDAK ada peran "Super Admin" tunggal
 * — kewenangan administratif dipecah antara HrAdmin (maker) dan
 * HrApprover (checker), sesuai pemisahan tugas §6.3.
 *
 * DirekturBidang/DirekturPembina ditambahkan setelah verifikasi
 * terhadap RA-001 v0.22 (DEC-92 versi awal: persetujuan LEMBUR
 * memotong hierarki langsung ke dua pejabat ini). KOREKSI kemudian:
 * Lembur sekarang 2 tahap — AtasanLangsung dulu, baru PimpinanKantor
 * (kepala unit/divisi atau KC/KCP pemohon) — lihat ApprovalQueueController.
 * DirekturBidang/DirekturPembina TIDAK dihapus dari enum ini (masih ada
 * data yang memegangnya), hanya berhenti dipakai untuk Lembur. Cuti
 * TETAP berjenjang AtasanLangsung(OFFICE_TREE)+HrApprover(BANK_WIDE)
 * seperti sebelumnya — tidak terpengaruh koreksi ini.
 */
enum Role: string
{
    /** Lingkup SELF — mengelola data dirinya sendiri. */
    case Pegawai = 'pegawai';

    /** Lingkup OFFICE_TREE — menyetujui Cuti bawahan di kantornya + kantor turunannya. */
    case AtasanLangsung = 'atasan_langsung';

    /** Lingkup OFFICE — maker: mengelola data induk pegawai di kantor yang ditugaskan. */
    case HrAdmin = 'hr_admin';

    /** Lingkup BANK_WIDE — checker: menyetujui/mengesahkan perubahan HrAdmin. */
    case HrApprover = 'hr_approver';

    /** Lingkup kantor bertipe head_office — satu-satunya pemutus Lembur unit Kantor Pusat (DEC-92). */
    case DirekturBidang = 'direktur_bidang';

    /** Lingkup kantor bertipe branch/sub_branch — satu-satunya pemutus Lembur seluruh KC/KCP (DEC-92). */
    case DirekturPembina = 'direktur_pembina';

    /**
     * Lingkup OFFICE — kepala kantor spesifik pemohon: Pimpinan Unit/
     * Divisi (office_type=head_office) atau Pimpinan KC/KCP (branch/
     * sub_branch). Tahap KEDUA persetujuan Lembur, SETELAH Atasan
     * Langsung (koreksi atas DEC-92 — bukan lagi memotong hierarki
     * langsung ke Direktur; lihat ApprovalQueueController). Satu nama
     * role dipakai seragam untuk kedua konteks — label UI berbeda
     * murni teks kontekstual berdasarkan office_type, BUKAN role
     * terpisah. Sengaja TERPISAH dari DirekturBidang/DirekturPembina
     * (level direksi, bukan kepala unit/cabang) — kedua role Direktur
     * itu tetap ada di enum ini tapi tidak lagi dipakai untuk Lembur.
     */
    case PimpinanKantor = 'pimpinan_kantor';

    /** Lingkup BANK_WIDE, hanya-baca — independen dari peran operasional lain. */
    case Auditor = 'auditor';

    /**
     * Lingkup teknis, BUKAN proses bisnis SDM: mengelola akun login
     * (reset kata sandi, penugasan peran) — sengaja terpisah dari
     * HrAdmin/HrApprover agar tidak menyamarkan §6.3 di balik istilah
     * "super admin". Tidak pernah melihat/mengubah data cuti, lembur,
     * atau data pegawai lain.
     */
    case SystemAdmin = 'system_admin';

    /**
     * Lingkup BANK_WIDE — mencairkan (membayarkan) SPPD yang SUDAH
     * disetujui. Sengaja peran TERSENDIRI, bukan menumpang HrApprover:
     * orang yang mengesahkan pengajuan tidak boleh menjadi orang yang
     * sama yang mencairkan dananya (§6.3) — ditegakkan di rekaman per
     * baris (DisburseSppdRequest menolak bila disbursed_by === approver_id),
     * bukan lewat pengecualian peran global seperti HrAdmin/HrApprover,
     * karena AtasanLangsung/HrApprover adalah peran struktural yang
     * dipegang banyak orang dan tidak semestinya diblokir total dari
     * fungsi bendahara.
     */
    case Treasury = 'treasury';

    public function label(): string
    {
        return match ($this) {
            self::Pegawai => 'Pegawai',
            self::AtasanLangsung => 'Atasan Langsung',
            self::HrAdmin => 'Admin SDM',
            self::HrApprover => 'Pejabat SDM (Approver)',
            self::DirekturBidang => 'Direktur Bidang',
            self::DirekturPembina => 'Direktur Pembina',
            self::PimpinanKantor => 'Pimpinan Unit/Divisi atau KC/KCP',
            self::Auditor => 'Auditor',
            self::SystemAdmin => 'Admin Sistem (IT)',
            self::Treasury => 'Bendahara (Pencairan)',
        };
    }

    public function defaultScopeType(): ScopeType
    {
        return match ($this) {
            self::Pegawai => ScopeType::SelfOnly,
            self::AtasanLangsung => ScopeType::OfficeTree,
            self::HrAdmin => ScopeType::Office,
            self::HrApprover, self::Auditor => ScopeType::BankWide,
            // Lingkup sesungguhnya (per tipe kantor) dihitung pemanggil
            // lewat EmployeeRepository::officeIdsByType() — bukan bentuk
            // OFFICE_TREE/BANK_WIDE baku, lihat ApprovalQueueController.
            self::DirekturBidang, self::DirekturPembina => ScopeType::OfficeTree,
            // Kepala kantor pemohon PERSIS (bukan pohon) — pola sama
            // seperti HrAdmin, hanya kantor sendiri yang dipimpin.
            self::PimpinanKantor => ScopeType::Office,
            // Lingkupnya akun sistem, bukan kantor — tidak dipakai oleh
            // AccessPolicy sama sekali (lihat SystemAdminUserController).
            self::SystemAdmin => ScopeType::BankWide,
            // Pencairan tidak mengenal batas kantor — dana dicairkan
            // terpusat, terlepas dari kantor pemohon.
            self::Treasury => ScopeType::BankWide,
        };
    }

    /** Auditor tidak boleh mengubah/menyetujui apa pun (§6.3 — independensi). */
    public function isReadOnly(): bool
    {
        return $this === self::Auditor;
    }
}
