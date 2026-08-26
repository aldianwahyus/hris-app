<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Trail — DB-001 §3.1.
 *
 * Sifat APPEND-ONLY. Ditegakkan pada tingkat hak akses basis data
 * (REVOKE UPDATE, DELETE), bukan hanya aplikasi. Koreksi dilakukan
 * dengan entri baru, bukan pengubahan.
 *
 * Dipartisi RANGE per bulan: menetapkan partisi sejak awal jauh lebih
 * murah daripada mengonversi tabel berisi jutaan baris di tahun ketiga.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabel perubahan data — dipartisi per bulan pada occurred_at
        DB::statement(<<<'SQL'
            CREATE TABLE aud_change_logs (
                id              UUID        NOT NULL,
                occurred_at     TIMESTAMPTZ NOT NULL,
                actor_id        UUID        NULL,
                actor_role      VARCHAR(100) NULL,
                auditable_type  VARCHAR(150) NOT NULL,
                auditable_id    UUID        NOT NULL,
                action          VARCHAR(50) NOT NULL,
                old_values      JSONB       NULL,
                new_values      JSONB       NULL,
                ip_address      INET        NULL,
                user_agent      TEXT        NULL,
                context_ref     VARCHAR(150) NULL,
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at);
        SQL);

        DB::statement('CREATE INDEX idx_aud_change_auditable ON aud_change_logs (auditable_type, auditable_id, occurred_at DESC);');
        DB::statement('CREATE INDEX idx_aud_change_actor ON aud_change_logs (actor_id, occurred_at DESC);');

        // Akses data sensitif dipisah: volumenya jauh lebih besar dan
        // retensinya berbeda. Menggabungkan keduanya membuat kueri audit
        // perubahan menjadi lambat tanpa manfaat.
        DB::statement(<<<'SQL'
            CREATE TABLE aud_access_logs (
                id             UUID        NOT NULL,
                occurred_at    TIMESTAMPTZ NOT NULL,
                actor_id       UUID        NOT NULL,
                resource_type  VARCHAR(150) NOT NULL,
                resource_id    UUID        NOT NULL,
                subject_id     UUID        NULL,
                access_type    VARCHAR(50) NOT NULL,
                ip_address     INET        NULL,
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at);
        SQL);

        DB::statement('CREATE INDEX idx_aud_access_subject ON aud_access_logs (subject_id, occurred_at DESC);');

        $this->createMonthlyPartitions('aud_change_logs');
        $this->createMonthlyPartitions('aud_access_logs');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS aud_access_logs CASCADE;');
        DB::statement('DROP TABLE IF EXISTS aud_change_logs CASCADE;');
    }

    /** Membuat partisi 12 bulan ke depan; pembuatan berkelanjutan dijadwalkan Scheduler. */
    private function createMonthlyPartitions(string $table): void
    {
        $start = new DateTimeImmutable('first day of this month 00:00:00');

        for ($i = 0; $i < 12; $i++) {
            $from = $start->modify("+{$i} months");
            $to = $from->modify('+1 month');
            $suffix = $from->format('Y_m');

            DB::statement(sprintf(
                'CREATE TABLE IF NOT EXISTS %s_%s PARTITION OF %s FOR VALUES FROM (%s) TO (%s);',
                $table,
                $suffix,
                $table,
                "'{$from->format('Y-m-d')}'",
                "'{$to->format('Y-m-d')}'"
            ));
        }
    }
};
