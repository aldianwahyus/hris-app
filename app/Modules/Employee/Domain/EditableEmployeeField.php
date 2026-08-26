<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

/**
 * Field emp_employees yang boleh diubah lewat maker-checker (Data
 * Pegawai, TOR Fase I). Sengaja whitelist tertutup — data identitas
 * dasar (nrp, full_name, birth_date, join_date) TIDAK termasuk: itu
 * koreksi data historis yang butuh proses berbeda (bukan mutasi
 * jabatan/kantor/status rutin), di luar cakupan ini.
 *
 * TunjanganJabatan/TunjanganPenyesuaian TIDAK punya tabel/kriteria
 * baku (beda dari Imbalan Kerja yang dihitung dari pay_salary_scale)
 * — karena itu editable di sini, dipakai langsung oleh
 * GajiPokokCalculator (lihat RunPayrollDraft).
 */
enum EditableEmployeeField: string
{
    case PositionId = 'position_id';
    case OfficeId = 'office_id';
    case EmploymentStatus = 'employment_status';
    case PersonGrade = 'person_grade';
    case SalaryStep = 'salary_step';
    case JobGrade = 'job_grade';
    case PermanentDate = 'permanent_date';
    case TunjanganJabatanCents = 'tunjangan_jabatan_cents';
    case TunjanganPenyesuaianCents = 'tunjangan_penyesuaian_cents';

    /** PTKP (PMK 168/2023, golongan TER) — wajib diisi agar PPh21 bisa dihitung, lihat RunPayrollDraft. */
    case MaritalStatus = 'marital_status';
    case Tanggungan = 'tanggungan';

    /**
     * MURNI untuk Struktur Organisasi (OrganizationChartController) —
     * TIDAK dipakai AccessPolicy/OrganizationalScope, wewenang tetap
     * berbasis lingkup kantor seperti sebelumnya.
     */
    case SupervisorId = 'supervisor_id';

    case Division = 'division';

    /**
     * Identitas & rekening HR — sengaja lewat maker-checker (BUKAN
     * self-edit CV Saya) walau sebagian terasa "data pribadi": tetap
     * perlu usul-setuju HR untuk data identitas resmi ini.
     */
    case Agama = 'agama';

    case NomorKtp = 'nomor_ktp';

    case NomorNpwp = 'nomor_npwp';

    case BpjsTenagaKerja = 'bpjs_tenaga_kerja';

    case BpjsKesehatan = 'bpjs_kesehatan';

    case NomorSimpeda = 'nomor_simpeda';

    case NomorTamboraRencana = 'nomor_tambora_rencana';

    /** TMT Pangkat — tanggal berlaku pangkat/jabatan terkini. */
    case TmtPangkat = 'tmt_pangkat';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $f) => $f->value, self::cases());
    }
}
