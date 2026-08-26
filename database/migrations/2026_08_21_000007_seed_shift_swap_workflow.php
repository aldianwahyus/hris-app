<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Definisi Workflow Engine untuk shift_swap_request — HANYA dipakai
 * untuk pelacakan tenggat SLA/pengingat (WorkflowInstanceRepository::
 * startFor()/loadSteps() TIDAK PERNAH membaca wf_step_resolvers, lihat
 * EloquentWorkflowInstanceRepository), BUKAN untuk menentukan siapa
 * yang berwenang memutus — itu tetap AccessPolicy/OrganizationalScope
 * manual di ShiftSwapApprovalController (atasan_langsung, 1 tahap,
 * office-tree — tukar shift tidak berdampak finansial/saldo cuti,
 * tidak butuh kontrol berlapis seperti Cuti/Lembur/SPPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        $definitionId = (string) Str::uuid();

        DB::table('wf_definitions')->insert([
            'id' => $definitionId,
            'document_type' => 'shift_swap_request',
            'name' => 'Persetujuan Tukar Shift',
            'revision' => 1,
            'effective_from' => '2026-01-01',
            'source_document' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        DB::table('wf_steps')->insert([
            'id' => (string) Str::uuid(),
            'definition_id' => $definitionId,
            'sequence' => 1,
            'name' => 'Keputusan Atasan Langsung',
            'approval_pattern' => 'individual',
            'quorum' => null,
            'require_unanimous' => false,
            'sla_days' => 7,
            'reminder_days_before' => json_encode([3, 1]),
            'allow_delegation' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    public function down(): void
    {
        $definitionId = DB::table('wf_definitions')->where('document_type', 'shift_swap_request')->value('id');

        if ($definitionId !== null) {
            DB::table('wf_steps')->where('definition_id', $definitionId)->delete();
            DB::table('wf_definitions')->where('id', $definitionId)->delete();
        }
    }
};
