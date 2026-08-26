<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ DATA CONTOH — peran Bendahara (Pencairan SPPD), lihat Role::Treasury.
 *
 * Mengikuti pola 2026_01_04_000001_seed_direktur_roles.php: Wave 1
 * belum memiliki data pegawai Finance/Treasury sungguhan, jadi satu
 * akun yang TIDAK memegang peran approver operasional apa pun
 * dipinjam SEMATA agar alur pencairan dapat dicoba ujung ke ujung —
 * menjaga agar pemisahan pencair/penyetuju tetap berarti pada data
 * contoh ini, bukan sekadar formalitas. Ganti begitu daftar pejabat
 * Finance/Treasury definitif tersedia.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'treasury',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dewi Lestari — Customer Service, KC Selong — tidak memegang
        // peran atasan_langsung/hr_approver/hr_admin apa pun.
        $employeeId = DB::table('emp_employees')->where('nrp', '2019.09.0177')->value('id');

        if ($employeeId === null) {
            return; // data contoh belum dijalankan — migrasi tetap idempoten
        }

        $userId = DB::table('users')->where('employee_id', $employeeId)->value('id');

        if ($userId === null) {
            return;
        }

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }

    public function down(): void
    {
        DB::table('model_has_roles')
            ->where('role_id', DB::table('roles')->where('name', 'treasury')->value('id'))
            ->delete();

        DB::table('roles')->where('name', 'treasury')->delete();
    }
};
