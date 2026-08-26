<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Konsolidasi Sanksi ke modul SK (keputusan sadar user) — emp_sanctions
 * DIPENSIUNKAN, SK (sk_type='sanksi') jadi satu-satunya jalur baru.
 * Baris lama disalin dulu (data tidak hilang) baru tabelnya di-drop.
 *
 * Data lama tidak punya nomor SK (field itu tidak pernah ada di
 * emp_sanctions) — diberi nomor placeholder "LEGACY-{8 karakter awal
 * id}" supaya tetap mengisi kolom sk_number yang NOT NULL, bukan
 * dikarang seolah-olah nomor SK asli.
 *
 * down() SENGAJA tidak mengembalikan data — drop tabel tidak reversibel
 * dengan aman (sama pola migrasi drop lain di proyek ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('emp_sanctions')->get();

        foreach ($rows as $row) {
            DB::table('emp_decision_letters')->insert([
                'id' => (string) Str::uuid(),
                'employee_id' => $row->employee_id,
                'sk_type' => 'sanksi',
                'sk_number' => 'LEGACY-'.substr($row->id, 0, 8),
                'sk_date' => $row->sanction_date,
                'effective_date' => null,
                'description' => $row->sanction_type.' — '.$row->reason,
                'document_path' => null,
                'document_original_name' => $row->reference_document,
                'profile_change_request_id' => null,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'version' => 1,
            ]);
        }

        Schema::dropIfExists('emp_sanctions');
    }

    public function down(): void
    {
        Schema::create('emp_sanctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('sanction_type', 50);
            $table->date('sanction_date');
            $table->text('reason');
            $table->string('reference_document', 150)->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees')->cascadeOnDelete();
            $table->index('employee_id');
        });
    }
};
