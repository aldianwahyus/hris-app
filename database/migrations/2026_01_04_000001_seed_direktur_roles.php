<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ DATA CONTOH — peran Direktur Bidang/Direktur Pembina (DEC-92).
 *
 * Wave 1 belum memiliki data pegawai setingkat Direktur (posisi
 * tertinggi yang di-seed adalah Division Head/Branch Manager). Sambil
 * menunggu data pejabat sesungguhnya dari Divisi HCD, dua akun paling
 * senior yang tersedia dipinjam sebagai pemegang peran ini — SEMATA
 * agar alur persetujuan lembur dapat dicoba ujung ke ujung. Ganti
 * begitu daftar pejabat definitif tersedia.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleIds = [];

        foreach (['direktur_bidang', 'direktur_pembina'] as $name) {
            $roleIds[$name] = DB::table('roles')->insertGetId([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Nur Aisyah — Division Head, Kantor Pusat (head_office) — dipinjam
        // sebagai Direktur Bidang.
        $this->assign($roleIds['direktur_bidang'], '2014.02.0061');

        // Ahmad Fauzi — Branch Manager, KC Mataram (branch) — dipinjam
        // sebagai Direktur Pembina.
        $this->assign($roleIds['direktur_pembina'], '2015.07.0088');
    }

    public function down(): void
    {
        DB::table('model_has_roles')
            ->whereIn('role_id', DB::table('roles')->whereIn('name', ['direktur_bidang', 'direktur_pembina'])->pluck('id'))
            ->delete();

        DB::table('roles')->whereIn('name', ['direktur_bidang', 'direktur_pembina'])->delete();
    }

    private function assign(int|string $roleId, string $nrp): void
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

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
};
