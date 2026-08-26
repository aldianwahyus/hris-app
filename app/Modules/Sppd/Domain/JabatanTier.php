<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

/**
 * Jenjang jabatan untuk tarif SPPD Pegawai (BPP/442/03/64/2026 §II.B).
 * BERBEDA dari Person Grade (Payroll) — tarif SPPD memakai
 * pengelompokan sendiri, hanya 5 jenjang.
 *
 * Tabel Pengurus (Direktur Utama/Anggota Direksi/Komisaris Utama/
 * Anggota Dekom-DPS) SENGAJA tidak diimplementasikan — belum ada satu
 * pun data pegawai berlevel Pengurus di Wave 1, jadi tidak pernah
 * terpakai. Tambahkan bila data pejabat Direksi sudah tersedia.
 */
enum JabatanTier: string
{
    case Sevp = 'sevp';
    case PejabatEksekutif = 'pejabat_eksekutif';
    case DeptHeadSbmMgr = 'dept_head_sbm_mgr';
    case TeamLeaderSpvOfficerStaff = 'team_leader_spv_officer_staff';
    case NonStaff = 'non_staff';

    public function label(): string
    {
        return match ($this) {
            self::Sevp => 'SEVP',
            self::PejabatEksekutif => 'Pejabat Eksekutif, Komite Dekom',
            self::DeptHeadSbmMgr => 'Dept Head, SBM, MGR/Setara',
            self::TeamLeaderSpvOfficerStaff => 'Team Leader, SPV, Officer, Staff/Setara',
            self::NonStaff => 'Non Staff',
        };
    }
}
