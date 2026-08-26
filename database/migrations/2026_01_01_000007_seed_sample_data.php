<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ⚠️ DATA CONTOH — BUKAN DATA SEBENARNYA.
 *
 * Daftar kantor dan pegawai asli belum diterima dari Divisi Human Capital
 * (lihat GAP-001). Data di bawah hanya agar antarmuka dapat dijalankan
 * dan dinilai lebih awal.
 *
 * Cara mengganti bila data asli sudah tersedia:
 *   php artisan migrate:rollback --step=1
 *   (ganti isi berkas ini, lalu migrate kembali)
 *
 * Nama pegawai sengaja generik dan tidak merujuk orang sungguhan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $offices = $this->seedOffices();
        $positions = $this->seedPositions();
        $employees = $this->seedEmployees($offices, $positions);

        $this->seedLeaveBalances($employees);
        $this->seedRequests($employees);
    }

    public function down(): void
    {
        DB::table('ovt_weekly_quotas')->delete();
        DB::table('ovt_requests')->delete();
        DB::table('leave_requests')->delete();
        DB::table('leave_balances')->delete();
        DB::table('emp_employees')->delete();
        DB::table('md_positions')->delete();
        DB::table('md_offices')->delete();
    }

    private function seedOffices(): array
    {
        $rows = [
            ['KP',      'Kantor Pusat',              'head_office', null,          'Asia/Makassar', -8.5833, 116.1167],
            ['KC-MTR',  'KC Mataram',                'branch',      'KC_UTAMA',    'Asia/Makassar', -8.5871, 116.1082],
            ['KC-SLG',  'KC Selong',                 'branch',      'KC_1',        'Asia/Makassar', -8.6500, 116.5333],
            ['KCP-PRY', 'KCP Praya',                 'sub_branch',  'KCP_1',       'Asia/Makassar', -8.7000, 116.2733],
            ['KCP-GRG', 'KCP Gerung',                'sub_branch',  'KCP_2',       'Asia/Makassar', -8.6667, 116.0500],
            // Satu-satunya kantor di luar WITA — pembeda zona waktu nyata
            ['KC-SBY',  'KC Surabaya',               'branch',      'KC_2',        'Asia/Jakarta',  -7.2575, 112.7521],
        ];

        $ids = [];

        foreach ($rows as [$code, $name, $type, $class, $tz, $lat, $lng]) {
            $id = (string) Str::uuid();
            $ids[$code] = $id;

            DB::table('md_offices')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'office_type' => $type,
                'office_class' => $class,
                'parent_office_id' => null,
                'timezone' => $tz,
                'latitude' => $lat,
                'longitude' => $lng,
                'geofence_radius_m' => 150,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }

        return $ids;
    }

    private function seedPositions(): array
    {
        // eligible_overtime_regular = false untuk lima jabatan yang
        // disebut eksplisit pada SK Upah Kerja Lembur.
        $rows = [
            ['DIV_HEAD', 'Division Head',      'support',  14, 15, false, true,  null],
            ['DEPT_HEAD','Department Head',    'support',  11, 13, false, true,  null],
            ['BM',       'Branch Manager',     'business', 11, 12, false, true,  null],
            ['SBM',      'Sub Branch Manager', 'business',  9, 10, false, true,  null],
            ['MGR',      'Manager',            'business',  9, 10, true,  true,  'MGR_SPV_OFC'],
            ['SPV',      'Supervisor',         'business',  7,  8, true,  true,  'MGR_SPV_OFC'],
            ['OFC',      'Officer',            'business',  5,  6, true,  true,  'MGR_SPV_OFC'],
            ['CS',       'Customer Service',   'business',  4,  5, true,  true,  'ASST_ADM'],
            ['TELLER',   'Teller',             'business',  4,  5, true,  true,  'ASST_ADM'],
            ['ADM',      'Asisten Administrasi','support',  3,  5, true,  true,  'ASST_ADM'],
            ['SATPAM',   'Satpam',             'support',   1,  2, true,  true,  'NON_ADMIN'],
        ];

        $ids = [];

        foreach ($rows as [$code, $name, $class, $min, $max, $reg, $crash, $rateClass]) {
            $id = (string) Str::uuid();
            $ids[$code] = $id;

            DB::table('md_positions')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'classification' => $class,
                'job_grade_min' => $min,
                'job_grade_max' => $max,
                'eligible_overtime_regular' => $reg,
                'eligible_overtime_crash' => $crash,
                'overtime_rate_class' => $rateClass,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }

        return $ids;
    }

    private function seedEmployees(array $offices, array $positions): array
    {
        $rows = [
            ['2018.03.0142', 'Siti Rahmawati',  'P', 'menikah',      '2018-03-01', '2019-03-01', 'KC-MTR',  'OFC',    8,  6],
            ['2015.07.0088', 'Ahmad Fauzi',     'L', 'menikah',      '2015-07-15', '2016-07-15', 'KC-MTR',  'BM',     14, 12],
            ['2020.01.0231', 'Budi Santoso',    'L', 'belum menikah','2020-01-06', '2021-01-06', 'KCP-PRY', 'TELLER', 6,  5],
            ['2019.09.0177', 'Dewi Lestari',    'P', 'menikah',      '2019-09-02', '2020-09-02', 'KC-SLG',  'CS',     6,  5],
            ['2021.05.0302', 'Rina Marlina',    'P', 'belum menikah','2021-05-03', '2022-05-03', 'KCP-GRG', 'ADM',    5,  4],
            ['2017.11.0119', 'Hendra Wijaya',   'L', 'menikah',      '2017-11-01', '2018-11-01', 'KC-MTR',  'SATPAM', 2,  1],
            ['2014.02.0061', 'Nur Aisyah',      'P', 'menikah',      '2014-02-03', '2015-02-03', 'KP',      'DIV_HEAD',16,14],
        ];

        $ids = [];

        foreach ($rows as [$nrp, $name, $gender, $marital, $join, $permanent, $office, $position, $pg, $jg]) {
            $id = (string) Str::uuid();
            $ids[$nrp] = ['id' => $id, 'name' => $name, 'position' => $position, 'office' => $office];

            DB::table('emp_employees')->insert([
                'id' => $id,
                'nrp' => $nrp,
                'full_name' => $name,
                'birth_date' => null,
                'gender' => $gender,
                'marital_status' => $marital,
                'join_date' => $join,
                'permanent_date' => $permanent,
                'employment_status' => 'tetap',
                'office_id' => $offices[$office],
                'position_id' => $positions[$position],
                'person_grade' => $pg,
                'job_grade' => $jg,
                'email' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }

        return $ids;
    }

    private function seedLeaveBalances(array $employees): void
    {
        $year = 2026;

        foreach ($employees as $nrp => $employee) {
            // Kuota mengikuti jenjang: di atas Manager 15 hari, lainnya 12 hari
            $aboveManager = in_array($employee['position'], ['DIV_HEAD', 'DEPT_HEAD', 'BM'], true);
            $quota = $aboveManager ? 15 : 12;

            $used = match ($nrp) {
                '2018.03.0142' => 3,
                '2019.09.0177' => 5,
                default => 0,
            };

            // Kantong 1: jatah tahun berjalan — MEMBERI hak bekal cuti
            DB::table('leave_balances')->insert([
                'id' => (string) Str::uuid(),
                'employee_id' => $employee['id'],
                'year' => $year,
                'bucket_type' => 'current_year',
                'quota_days' => $quota,
                'used_days' => $used,
                'expires_on' => "{$year}-12-31",
                'triggers_allowance' => true,
                'consumption_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);

            // Kantong 2: sisa tahun lalu — TIDAK memberi hak bekal cuti,
            // hangus 31 Maret
            DB::table('leave_balances')->insert([
                'id' => (string) Str::uuid(),
                'employee_id' => $employee['id'],
                'year' => $year,
                'bucket_type' => 'carry_forward',
                'quota_days' => 2,
                'used_days' => 0,
                'expires_on' => "{$year}-03-31",
                'triggers_allowance' => false,
                'consumption_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }
    }

    private function seedRequests(array $employees): void
    {
        $byNrp = fn (string $nrp) => $employees[$nrp]['id'];

        // Cuti
        DB::table('leave_requests')->insert([
            [
                'id' => (string) Str::uuid(),
                'request_number' => 'CT/2026/08/0034',
                'employee_id' => $byNrp('2018.03.0142'),
                'leave_type' => 'CT',
                'start_date' => '2026-08-18',
                'end_date' => '2026-08-20',
                'total_days' => 3,
                'reason' => 'Keperluan keluarga di Sumbawa',
                'status' => 'approved',
                'approver_id' => $byNrp('2015.07.0088'),
                'decided_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ],
            [
                'id' => (string) Str::uuid(),
                'request_number' => 'CT/2026/08/0041',
                'employee_id' => $byNrp('2019.09.0177'),
                'leave_type' => 'CT',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-05',
                'total_days' => 5,
                'reason' => 'Cuti tahunan',
                'status' => 'pending',
                'approver_id' => null,
                'decided_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ],
        ]);

        // Lembur — tenggat sengaja bervariasi agar penanda batas waktu
        // pada antrean persetujuan terlihat bekerja.
        $overtime = [
            ['SPKL/2026/07/0298', '2020.01.0231', 'regular',       '2026-07-15', 4.0, 2500000, '2026-08-14'],
            ['SPKL/2026/07/0311', '2019.09.0177', 'regular',       '2026-07-17', 3.0, 2500000, '2026-08-16'],
            ['SPKL/2026/08/0402', '2015.07.0088', 'crash_program', '2026-08-02', 1.0, null,     '2026-09-01'],
            ['SPKL/2026/08/0415', '2021.05.0302', 'regular',       '2026-08-05', 2.0, 2500000, '2026-09-04'],
            ['SPKL/2026/08/0428', '2017.11.0119', 'regular',       '2026-08-08', 3.0, 2000000, '2026-09-07'],
            ['SPKL/2026/08/0412', '2018.03.0142', 'regular',       '2026-08-12', 3.0, 3000000, '2026-09-11'],
        ];

        foreach ($overtime as [$spkl, $nrp, $type, $workDate, $hours, $rateCents, $deadline]) {
            $amount = $type === 'crash_program'
                ? 25_000_000                       // Rp250.000 lumpsum per hari
                : (int) ($rateCents * $hours);

            DB::table('ovt_requests')->insert([
                'id' => (string) Str::uuid(),
                'spkl_number' => $spkl,
                'employee_id' => $byNrp($nrp),
                'overtime_type' => $type,
                'work_date' => $workDate,
                'planned_hours' => $hours,
                'payable_hours' => $hours,
                'hourly_rate_cents' => $rateCents,
                'amount_cents' => $amount,
                'status' => 'pending',
                'approver_id' => null,
                'approval_deadline' => $deadline,
                'decided_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }
    }
};
