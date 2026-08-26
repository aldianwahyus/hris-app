<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 5 — Payroll & Pajak (sebagian, per BPP/137/03/64/2026).
 *
 * pay_salary_scale: Lampiran II (Tabel Skala Imbalan Kerja), matriks
 * person_grade × baris. BERVERSI seperti cfg_parameter_values —
 * dilindungi EXCLUDE constraint yang sama agar dua nilai tidak pernah
 * berlaku bersamaan untuk kombinasi grade+baris yang sama.
 *
 * pay_payroll_runs / pay_payslips: maker-checker seperti Cuti/Lembur —
 * Admin SDM (maker) menjalankan draf per kantor+periode, Pejabat SDM
 * (checker) menyetujui. Lingkupnya OFFICE (union bank-wide untuk
 * checker), BUKAN Direktur Bidang/Pembina — ini proses SDM, bukan
 * Lembur (DEC-92 tidak berlaku di sini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emp_employees', function (Blueprint $table) {
            // "Baris" pada skala imbalan kerja — naik lewat evaluasi
            // kinerja berkala (BPP §A.5). Wave 1 tidak punya riwayat
            // evaluasi, jadi seluruh pegawai contoh dimulai dari 1.
            $table->unsignedTinyInteger('salary_step')->default(1)->after('job_grade');
        });

        Schema::create('pay_salary_scale', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('person_grade');
            $table->unsignedTinyInteger('step');
            $table->bigInteger('amount_cents');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_document', 150)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');
        DB::statement(<<<'SQL'
            ALTER TABLE pay_salary_scale
            ADD CONSTRAINT excl_pay_salary_scale_overlap
            EXCLUDE USING gist (
                person_grade WITH =,
                step WITH =,
                daterange(effective_from, effective_to, '[]') WITH &&
            ) WHERE (deleted_at IS NULL);
        SQL);

        Schema::create('pay_payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('office_id');
            $table->date('period'); // selalu tanggal 1 pada bulan berjalan
            $table->string('status', 20)->default('draft'); // draft | approved | rejected
            $table->uuid('created_by'); // employee_id Admin SDM (maker)
            $table->uuid('approved_by')->nullable(); // employee_id Pejabat SDM (checker)
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices');
            $table->unique(['office_id', 'period'], 'uq_pay_runs_office_period');
        });

        Schema::create('pay_payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_run_id');
            $table->uuid('employee_id');
            $table->unsignedTinyInteger('person_grade');
            $table->unsignedTinyInteger('salary_step');

            $table->bigInteger('imbalan_kerja_cents');
            $table->bigInteger('iuran_pensiun_pegawai_cents');
            $table->bigInteger('iuran_tht_pegawai_cents');
            $table->bigInteger('iuran_tht_bank_cents');
            $table->bigInteger('take_home_partial_cents');

            // Daftar komponen yang belum dapat dihitung (Tunjangan
            // Jabatan/Kinerja/Kemahalan, Astek, PPh21) — lihat
            // Domain/PayslipComponents::pendingComponents(). Disimpan
            // agar slip yang sudah diterbitkan tetap menunjukkan status
            // ketidaklengkapannya SAAT ITU, bukan status hari ini.
            $table->json('pending_components');

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('payroll_run_id')->references('id')->on('pay_payroll_runs')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->unique(['payroll_run_id', 'employee_id'], 'uq_pay_payslips_run_employee');
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_payslips');
        Schema::dropIfExists('pay_payroll_runs');
        Schema::dropIfExists('pay_salary_scale');

        Schema::table('emp_employees', function (Blueprint $table) {
            $table->dropColumn('salary_step');
        });
    }
};
