<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti Wave 1 — versi minimal yang cukup untuk antarmuka berjalan.
 *
 * Struktur mengikuti DB-001. Kolom yang bergantung pada data yang belum
 * diterima (tabel skala imbalan kerja, koordinat kantor sebenarnya)
 * disiapkan namun dibiarkan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- Kantor ----------
        Schema::create('md_offices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('office_type', 20);        // head_office | branch | sub_branch | functional
            $table->string('office_class', 30)->nullable(); // KC_UTAMA | KC_1 | KC_2 | KCP_KHUSUS | KCP_1 | KCP_2
            $table->uuid('parent_office_id')->nullable();

            // Bank beroperasi lintas zona waktu: mayoritas WITA,
            // KC Surabaya berada di WIB. Disimpan per kantor, bukan
            // per sistem — kesalahan di sini membuat tanggal absensi salah.
            $table->string('timezone', 40)->default('Asia/Makassar');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('geofence_radius_m')->default(100);

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->index('office_type');
        });

        // ---------- Jabatan ----------
        Schema::create('md_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('classification', 20)->nullable();   // business | support
            $table->unsignedTinyInteger('job_grade_min')->nullable();
            $table->unsignedTinyInteger('job_grade_max')->nullable();

            // Kelayakan lembur = atribut jabatan, bukan rumus person grade.
            // SK menyebut lima jabatan secara eksplisit, sehingga disimpan
            // sebagai penanda agar perubahan kebijakan cukup lewat data.
            $table->boolean('eligible_overtime_regular')->default(true);
            $table->boolean('eligible_overtime_crash')->default(true);
            $table->string('overtime_rate_class', 20)->nullable(); // MGR_SPV_OFC | ASST_ADM | NON_ADMIN

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);
        });

        // ---------- Pegawai ----------
        Schema::create('emp_employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nrp', 30)->unique();
            $table->string('full_name', 150);
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();

            // Dua tanggal BERBEDA dengan konsekuensi berbeda:
            //   join_date      -> dasar Masa Kerja Riil (PMB 15/20/25/30/35 th)
            //   permanent_date -> dasar hak Cuti Besar (6 tahun berturut-turut)
            // Menggabungkannya menghasilkan hak yang salah bagi eks-trainee.
            $table->date('join_date');
            $table->date('permanent_date')->nullable();

            $table->string('employment_status', 20)->default('tetap'); // tetap|trainee|kontrak|outsource
            $table->uuid('office_id');
            $table->uuid('position_id');
            $table->unsignedTinyInteger('person_grade')->nullable();
            $table->unsignedTinyInteger('job_grade')->nullable();
            $table->string('email', 150)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('office_id')->references('id')->on('md_offices');
            $table->foreign('position_id')->references('id')->on('md_positions');
            $table->index(['office_id', 'employment_status']);
        });

        // ---------- Saldo cuti (pola kantong) ----------
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->unsignedSmallInteger('year');

            // Saldo cuti BUKAN satu angka. Tiga kantong dengan sifat berbeda:
            //   current_year  -> memberi hak bekal cuti
            //   carry_forward -> TIDAK memberi hak bekal cuti, hangus 31 Maret
            //   long_leave    -> cuti besar
            // Menggabungkannya langsung menimbulkan kesalahan pembayaran.
            $table->string('bucket_type', 20);

            $table->decimal('quota_days', 5, 1);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->date('expires_on')->nullable();
            $table->boolean('triggers_allowance')->default(false);
            $table->unsignedTinyInteger('consumption_order')->default(1);

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->unique(['employee_id', 'year', 'bucket_type'], 'uq_leave_balances_emp_year_bucket');
        });

        // ---------- Pengajuan cuti ----------
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 40)->unique();
            $table->uuid('employee_id');
            $table->string('leave_type', 10);          // CT|CB|CS|CBS|CGK|CAP|CIB|CLT|CBR
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('approver_id')->nullable();
            $table->timestampTz('decided_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->index(['employee_id', 'status']);
            $table->index(['approver_id', 'status']);
        });

        // ---------- Pengajuan lembur ----------
        Schema::create('ovt_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('spkl_number', 40)->unique();
            $table->uuid('employee_id');
            $table->string('overtime_type', 20);       // regular | crash_program | shift_picket
            $table->date('work_date');
            $table->decimal('planned_hours', 4, 1)->nullable();
            $table->decimal('payable_hours', 4, 1)->nullable();
            $table->bigInteger('hourly_rate_cents')->nullable();
            $table->bigInteger('amount_cents')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('approver_id')->nullable();

            // Batas 30 hari sejak pelaksanaan. Melewati ini, hak bayar
            // pegawai HANGUS meski pekerjaan sudah dilakukan.
            $table->date('approval_deadline');
            $table->timestampTz('decided_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->index(['status', 'approval_deadline']);   // dasbor antrean + pengingat SLA
        });

        // ---------- Kuota lembur mingguan (objek penguncian) ----------
        Schema::create('ovt_weekly_quotas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->date('week_start_date');           // selalu hari Senin
            $table->decimal('approved_hours', 4, 1)->default(0);
            $table->decimal('pending_hours', 4, 1)->default(0);

            $table->timestampsTz();
            $table->integer('version')->default(1);

            $table->foreign('employee_id')->references('id')->on('emp_employees');
            $table->unique(['employee_id', 'week_start_date'], 'uq_ovt_weekly_emp_week');
        });

        // Plafon 18 jam/minggu ditegakkan di basis data — pertahanan
        // terakhir yang tidak dapat ditembus jalur mana pun.
        DB::statement(<<<'SQL'
            ALTER TABLE ovt_weekly_quotas
            ADD CONSTRAINT ck_ovt_weekly_cap
            CHECK (approved_hours + pending_hours <= 18);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ovt_weekly_quotas');
        Schema::dropIfExists('ovt_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('emp_employees');
        Schema::dropIfExists('md_positions');
        Schema::dropIfExists('md_offices');
    }
};
