<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Definisi alur persetujuan untuk TIGA POLA nyata.
 *
 * Seluruhnya disimpan sebagai DATA — memenuhi syarat Project Constitution
 * "Workflow tidak boleh di-hardcode". Perubahan matriks kewenangan cukup
 * lewat pembaruan baris, tanpa deployment ulang.
 *
 * Sumber aturan:
 *   - Cuti    : BPP/1087/03/64/2026, matriks Kewenangan Memutus Cuti
 *   - Lembur  : KEP/1827/06/64/2026, persetujuan Direktur Bidang/Pembina
 *   - Sanksi  : SPP Sanksi, Komite Pertimbangan Pelanggaran
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->seedLeaveWorkflow();
        $this->seedOvertimeWorkflow();
        $this->seedViolationWorkflow();
    }

    public function down(): void
    {
        $types = ['leave_request', 'overtime_request', 'violation_case'];

        $ids = DB::table('wf_definitions')->whereIn('document_type', $types)->pluck('id');

        DB::table('wf_step_resolvers')
            ->whereIn('step_id', DB::table('wf_steps')->whereIn('definition_id', $ids)->pluck('id'))
            ->delete();
        DB::table('wf_steps')->whereIn('definition_id', $ids)->delete();
        DB::table('wf_definitions')->whereIn('id', $ids)->delete();
    }

    /**
     * POLA 1 — INDIVIDUAL BERJENJANG (Cuti).
     *
     * Approver ditentukan matriks (jenis cuti × jabatan × lokasi kantor).
     * Pegawai KC diputus Branch Manager; pegawai KCP diputus Sub Branch
     * Manager; pegawai Kantor Pusat diputus Division/Desk Head.
     *
     * Jenis cuti tertentu (sakit, alasan penting, ibadah, di luar
     * tanggungan) NAIK LANGSUNG ke Direksi — karena itu kondisinya
     * dinyatakan eksplisit pada resolver.
     */
    private function seedLeaveWorkflow(): void
    {
        $definitionId = (string) Str::uuid();

        DB::table('wf_definitions')->insert([
            'id' => $definitionId,
            'document_type' => 'leave_request',
            'name' => 'Persetujuan Pengajuan Cuti',
            'revision' => 1,
            'effective_from' => '2026-03-10',
            'source_document' => 'BPP/1087/03/64/2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $stepId = (string) Str::uuid();

        DB::table('wf_steps')->insert([
            'id' => $stepId,
            'definition_id' => $definitionId,
            'sequence' => 1,
            'name' => 'Keputusan Pejabat Pemutus',
            'approval_pattern' => 'individual',
            'quorum' => null,
            'require_unanimous' => false,
            'sla_days' => 5,
            'reminder_days_before' => json_encode([3, 1]),
            'allow_delegation' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        // Matriks kewenangan. Priority lebih kecil dievaluasi lebih dulu,
        // sehingga aturan khusus (naik ke Direksi) menang atas aturan umum.
        $resolvers = [
            [
                'resolver_type' => 'fixed_role',
                'subject_scope' => null,
                'role_code' => 'DIREKSI',
                'conditions' => json_encode([
                    'leave_type' => ['CS', 'CAP', 'CIB', 'CLT'],
                ]),
                'priority' => 10,
            ],
            [
                'resolver_type' => 'position',
                'subject_scope' => 'head_office',
                'role_code' => 'DIVISION_HEAD',
                'conditions' => json_encode([
                    'leave_type' => ['CT', 'CB', 'CBS', 'CGK'],
                ]),
                'priority' => 20,
            ],
            [
                'resolver_type' => 'position',
                'subject_scope' => 'branch',
                'role_code' => 'BRANCH_MANAGER',
                'conditions' => json_encode([
                    'leave_type' => ['CT', 'CB', 'CBS', 'CGK'],
                ]),
                'priority' => 20,
            ],
            [
                'resolver_type' => 'position',
                'subject_scope' => 'sub_branch',
                'role_code' => 'SUB_BRANCH_MANAGER',
                'conditions' => json_encode([
                    'leave_type' => ['CT', 'CB', 'CBS', 'CGK'],
                ]),
                'priority' => 20,
            ],
        ];

        foreach ($resolvers as $resolver) {
            DB::table('wf_step_resolvers')->insert(array_merge($resolver, [
                'id' => (string) Str::uuid(),
                'step_id' => $stepId,
                'position_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * POLA 2 — TUNGGAL TERPUSAT (Lembur).
     *
     * MEMOTONG hierarki: pengajuan tidak naik ke atasan langsung,
     * melainkan langsung ke Direktur Bidang (pegawai Kantor Pusat)
     * atau Direktur Pembina (pegawai KC/KCP).
     *
     * SLA 30 hari mengikuti batas retroaktif: lembur yang belum
     * disetujui dalam 30 hari sejak pelaksanaan TIDAK DAPAT DIBAYARKAN.
     * Pengingat H-7 dan H-3 adalah mitigasi wajib — tanpa itu pegawai
     * kehilangan upah atas pekerjaan yang sudah dilakukan (risiko RA-3).
     */
    private function seedOvertimeWorkflow(): void
    {
        $definitionId = (string) Str::uuid();

        DB::table('wf_definitions')->insert([
            'id' => $definitionId,
            'document_type' => 'overtime_request',
            'name' => 'Persetujuan Upah Kerja Lembur',
            'revision' => 1,
            'effective_from' => '2026-07-01',
            'source_document' => 'KEP/1827/06/64/2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $stepId = (string) Str::uuid();

        DB::table('wf_steps')->insert([
            'id' => $stepId,
            'definition_id' => $definitionId,
            'sequence' => 1,
            'name' => 'Keputusan Direktur Bidang / Pembina',
            'approval_pattern' => 'centralized',
            'quorum' => null,
            'require_unanimous' => false,
            'sla_days' => 30,
            'reminder_days_before' => json_encode([7, 3]),
            'allow_delegation' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $resolvers = [
            [
                'resolver_type' => 'fixed_role',
                'subject_scope' => 'head_office',
                'role_code' => 'DIREKTUR_BIDANG',
                'conditions' => null,
                'priority' => 10,
            ],
            [
                'resolver_type' => 'fixed_role',
                'subject_scope' => 'branch',
                'role_code' => 'DIREKTUR_PEMBINA',
                'conditions' => null,
                'priority' => 10,
            ],
            [
                'resolver_type' => 'fixed_role',
                'subject_scope' => 'sub_branch',
                'role_code' => 'DIREKTUR_PEMBINA',
                'conditions' => null,
                'priority' => 10,
            ],
        ];

        foreach ($resolvers as $resolver) {
            DB::table('wf_step_resolvers')->insert(array_merge($resolver, [
                'id' => (string) Str::uuid(),
                'step_id' => $stepId,
                'position_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * POLA 3 — KOMITE / KUORUM (Pelanggaran & Sanksi).
     *
     * Keputusan kolektif, bukan satu approver per tahap. Komite
     * Pertimbangan Pelanggaran bersidang dengan syarat kuorum;
     * hasilnya kemudian ditetapkan Direksi.
     *
     * Dua langkah berurutan: sidang komite, lalu penetapan.
     */
    private function seedViolationWorkflow(): void
    {
        $definitionId = (string) Str::uuid();

        DB::table('wf_definitions')->insert([
            'id' => $definitionId,
            'document_type' => 'violation_case',
            'name' => 'Penanganan Pelanggaran & Penetapan Sanksi',
            'revision' => 1,
            'effective_from' => '2026-01-01',
            'source_document' => 'SPP Sanksi Pegawai',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        // Langkah 1 — sidang komite dengan kuorum 3
        $sidangId = (string) Str::uuid();

        DB::table('wf_steps')->insert([
            'id' => $sidangId,
            'definition_id' => $definitionId,
            'sequence' => 1,
            'name' => 'Sidang Komite Pertimbangan Pelanggaran',
            'approval_pattern' => 'committee',
            'quorum' => 3,
            'require_unanimous' => false,
            'sla_days' => 14,
            'reminder_days_before' => json_encode([7, 3]),
            'allow_delegation' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        DB::table('wf_step_resolvers')->insert([
            'id' => (string) Str::uuid(),
            'step_id' => $sidangId,
            'resolver_type' => 'committee_member',
            'subject_scope' => null,
            'position_id' => null,
            'role_code' => 'KPP_MEMBER',
            'conditions' => null,
            'priority' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Langkah 2 — penetapan oleh Direksi
        $penetapanId = (string) Str::uuid();

        DB::table('wf_steps')->insert([
            'id' => $penetapanId,
            'definition_id' => $definitionId,
            'sequence' => 2,
            'name' => 'Penetapan Sanksi oleh Direksi',
            'approval_pattern' => 'individual',
            'quorum' => null,
            'require_unanimous' => false,
            'sla_days' => 7,
            'reminder_days_before' => json_encode([3]),
            'allow_delegation' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        DB::table('wf_step_resolvers')->insert([
            'id' => (string) Str::uuid(),
            'step_id' => $penetapanId,
            'resolver_type' => 'fixed_role',
            'subject_scope' => null,
            'position_id' => null,
            'role_code' => 'DIREKSI',
            'conditions' => null,
            'priority' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
