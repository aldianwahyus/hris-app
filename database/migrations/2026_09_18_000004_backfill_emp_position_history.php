<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Baris AWAL riwayat posisi untuk setiap pegawai yang SUDAH ADA saat
 * fitur ini dirilis — sistem lama tidak pernah merekam riwayat posisi,
 * jadi baris ini BUKAN riwayat sesungguhnya, melainkan proyeksi mundur
 * posisi TERKINI, dedated di join_date (atau tanggal migrasi ini bila
 * join_date entah bagaimana kosong). Laporan Record Pegawai
 * menampilkan peringatan eksplisit untuk bulan sebelum tanggal rilis
 * migrasi ini (lihat EmployeePositionRecordController) — kejujuran ini
 * SENGAJA, bukan kelalaian. Baris berikutnya (riwayat ASLI) mulai
 * terekam otomatis lewat hook baru di
 * DecideEmployeeProfileChange::approve().
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $employees = DB::table('emp_employees')
            ->select('id', 'office_id', 'position_id', 'person_grade', 'job_grade', 'join_date')
            ->get();

        foreach ($employees as $employee) {
            DB::table('emp_position_history')->insert([
                'id' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'office_id' => $employee->office_id,
                'position_id' => $employee->position_id,
                'person_grade' => $employee->person_grade,
                'job_grade' => $employee->job_grade,
                'effective_from' => $employee->join_date ?? $now->toDateString(),
                'decision_letter_id' => null,
                'created_at' => $now,
                'version' => 1,
            ]);
        }
    }

    /**
     * SENGAJA tidak menghapus apa pun (pola sama
     * 2026_08_27_000002_migrate_sanctions_to_decision_letters.php) —
     * baris `decision_letter_id IS NULL` yang ditambahkan migrasi ini
     * TIDAK BISA dibedakan secara aman dari baris riwayat ASLI yang
     * mungkin sudah ditambahkan aplikasi (lewat DecideEmployeeProfileChange
     * ::approve()) sejak migrasi ini jalan — perubahan posisi lewat
     * jalur maker-checker biasa TANPA SK formal juga sah punya
     * decision_letter_id null. Rollback skema (migrasi
     * 000003::down()) sudah cukup menghapus SELURUH tabel bila memang
     * diperlukan.
     */
    public function down(): void {}
};
