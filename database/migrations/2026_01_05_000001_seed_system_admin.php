<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ⚠️ DATA CONTOH — akun Admin Sistem (IT).
 *
 * Peran ini SENGAJA dipisah dari lima peran SDM Wave 1: bukan "Super
 * Admin" yang melihat segalanya (§6.3 tidak mengenal peran itu),
 * melainkan akun teknis yang hanya mengelola akun login (reset kata
 * sandi, penugasan peran) — tidak pernah menyentuh data cuti/lembur.
 *
 * Tetap butuh baris emp_employees agar bisa masuk lewat jalur NRP +
 * kata sandi yang sama (DEC-02: satu-satunya identity provider, satu
 * jalur verifikasi kredensial) — bukan pintu masuk terpisah.
 */
return new class extends Migration
{
    private const DEMO_PASSWORD = 'RahasiaDemo!123';

    public function up(): void
    {
        $officeId = DB::table('md_offices')->where('code', 'KP')->value('id');

        if ($officeId === null) {
            return; // data contoh belum dijalankan — migrasi tetap idempoten
        }

        $positionId = (string) Str::uuid();
        DB::table('md_positions')->insert([
            'id' => $positionId,
            'code' => 'SYS_ADMIN',
            'name' => 'Administrator Sistem',
            'classification' => 'support',
            'job_grade_min' => null,
            'job_grade_max' => null,
            // Bukan jabatan operasional bank — tidak pernah mengajukan
            // lembur, jadi sengaja tidak berhak sama sekali.
            'eligible_overtime_regular' => false,
            'eligible_overtime_crash' => false,
            'overtime_rate_class' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $employeeId = (string) Str::uuid();
        DB::table('emp_employees')->insert([
            'id' => $employeeId,
            'nrp' => 'SYSADMIN',
            'full_name' => 'Administrator Sistem',
            'birth_date' => null,
            'gender' => null,
            'marital_status' => null,
            'join_date' => now()->toDateString(),
            'permanent_date' => null,
            'employment_status' => 'tetap',
            'office_id' => $officeId,
            'position_id' => $positionId,
            'person_grade' => null,
            'job_grade' => null,
            'email' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'system_admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'employee_id' => $employeeId,
            'name' => 'Administrator Sistem',
            'email' => 'sysadmin@bankntbsyariah.demo',
            'password' => Hash::make(self::DEMO_PASSWORD),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // TIDAK diberi peran "pegawai" — bukan pegawai SDM sungguhan,
        // hanya menumpang tabel emp_employees agar login NRP berfungsi.
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }

    public function down(): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', 'SYSADMIN')->value('id');

        if ($employeeId !== null) {
            $userId = DB::table('users')->where('employee_id', $employeeId)->value('id');

            if ($userId !== null) {
                DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $userId)->delete();
                DB::table('users')->where('id', $userId)->delete();
            }

            DB::table('emp_employees')->where('id', $employeeId)->delete();
        }

        DB::table('roles')->where('name', 'system_admin')->delete();
        DB::table('md_positions')->where('code', 'SYS_ADMIN')->delete();
    }
};
