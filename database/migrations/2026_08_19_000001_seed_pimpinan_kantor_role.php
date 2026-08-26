<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi alur Lembur: 2 tahap (AtasanLangsung dulu, baru PimpinanKantor)
 * menggantikan DEC-92 versi awal yang memotong langsung ke Direktur
 * Bidang/Pembina — lihat ApprovalQueueController.
 *
 * ⚠️ DATA CONTOH — dipinjam dari jabatan yang SUDAH cocok gelarnya:
 * Nur Aisyah (Division Head, Kantor Pusat) dan Ahmad Fauzi (Branch
 * Manager, KC Mataram) SUDAH persis "pimpinan unit/divisi"/"pimpinan
 * KC" berdasarkan posisi mereka di data contoh — bukan pinjaman
 * sembarangan seperti direktur_bidang/direktur_pembina dulu (data
 * contoh belum ada level Direktur sama sekali).
 *
 * CATATAN: Ahmad Fauzi jadinya memegang atasan_langsung DAN
 * pimpinan_kantor sekaligus untuk KC Mataram — realistis untuk cabang
 * sekecil ini (hanya dia satu-satunya jabatan manajerial di sana pada
 * data contoh). Guard swa-putus lintas-tingkat di ApprovalQueueController
 * MEMANG akan mencegah dia memutus kedua tahap pengajuan yang sama —
 * itu benar secara desain, bukan bug: kalau situasi nyata seperti ini
 * terjadi, prosesnya butuh pejabat pengganti, bukan dilonggarkan sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'pimpinan_kantor',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assign($roleId, '2014.02.0061'); // Nur Aisyah — Division Head, Kantor Pusat
        $this->assign($roleId, '2015.07.0088'); // Ahmad Fauzi — Branch Manager, KC Mataram
    }

    public function down(): void
    {
        DB::table('model_has_roles')
            ->whereIn('role_id', DB::table('roles')->where('name', 'pimpinan_kantor')->pluck('id'))
            ->delete();

        DB::table('roles')->where('name', 'pimpinan_kantor')->delete();
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
