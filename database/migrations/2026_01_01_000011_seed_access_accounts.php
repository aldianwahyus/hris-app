<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ⚠️ DATA CONTOH — lima peran (ARCH-001 §6.2) + akun demo agar alur
 * masuk Tahap 1 dapat dicoba ujung ke ujung.
 *
 * Mengikuti pola 2026_01_01_000007_seed_sample_data.php: kredensial
 * sungguhan menyusul bersama data pegawai asli dari Divisi Human
 * Capital. Kata sandi seragam HANYA untuk lingkungan pengembangan.
 */
return new class extends Migration
{
    private const DEMO_PASSWORD = '  ';

    public function up(): void
    {
        // Hierarki kantor minimal agar lingkup OFFICE_TREE (§6.2) dapat
        // diperagakan: KCP berada di bawah KC induknya.
        $this->linkOfficeHierarchy();

        $roleIds = $this->seedRoles();
        $this->seedAccounts($roleIds);
    }

    public function down(): void
    {
        DB::table('model_has_roles')->where('model_type', User::class)->delete();
        DB::table('users')->whereNotNull('employee_id')->delete();

        DB::table('roles')->whereIn('name', [
            'pegawai', 'atasan_langsung', 'hr_admin', 'hr_approver', 'auditor',
        ])->delete();

        DB::table('md_offices')->whereIn('code', ['KCP-PRY', 'KCP-GRG'])
            ->update(['parent_office_id' => null]);
    }

    private function linkOfficeHierarchy(): void
    {
        $officeId = fn (string $code) => DB::table('md_offices')->where('code', $code)->value('id');

        DB::table('md_offices')->where('code', 'KCP-PRY')
            ->update(['parent_office_id' => $officeId('KC-MTR')]);

        DB::table('md_offices')->where('code', 'KCP-GRG')
            ->update(['parent_office_id' => $officeId('KC-SLG')]);
    }

    /** @return array<string, int|string> nama peran => id */
    private function seedRoles(): array
    {
        $ids = [];

        foreach (['pegawai', 'atasan_langsung', 'hr_admin', 'hr_approver', 'auditor'] as $name) {
            $ids[$name] = DB::table('roles')->insertGetId([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /** @param array<string, int|string> $roleIds */
    private function seedAccounts(array $roleIds): void
    {
        // NRP => peran tambahan (di luar "pegawai", yang otomatis
        // dimiliki semua akun — lingkup SELF pada ESS-nya sendiri).
        $assignments = [
            '2018.03.0142' => [],                    // Officer, KC Mataram
            '2015.07.0088' => ['atasan_langsung'],    // Branch Manager, KC Mataram — menyetujui KC-MTR + KCP-PRY
            '2020.01.0231' => ['auditor'],            // Teller, KCP Praya — independen, tanpa peran operasional lain
            '2019.09.0177' => [],                     // CS, KC Selong
            '2021.05.0302' => ['hr_admin'],           // Adm, KCP Gerung — maker data induk pegawai
            '2017.11.0119' => [],                     // Satpam, KC Mataram
            '2014.02.0061' => ['hr_approver'],        // Division Head, Kantor Pusat — checker
        ];

        foreach ($assignments as $nrp => $extraRoles) {
            $employee = DB::table('emp_employees')->where('nrp', $nrp)->first();

            if ($employee === null) {
                continue; // data contoh belum dijalankan — migrasi tetap idempoten
            }

            $userId = DB::table('users')->insertGetId([
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'email' => str_replace(' ', '', $nrp).'@bankntbsyariah.demo',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ([...$extraRoles, 'pegawai'] as $role) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleIds[$role],
                    'model_type' => User::class,
                    'model_id' => $userId,
                ]);
            }
        }
    }
};
