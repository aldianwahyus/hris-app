<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Definisi Workflow Engine untuk outside_attendance_request — HANYA
 * dipakai untuk pelacakan tenggat SLA/pengingat (WorkflowInstanceRepository
 * TIDAK PERNAH membaca wf_step_resolvers), BUKAN untuk menentukan siapa
 * yang berwenang memutus — itu tetap AccessPolicy/OrganizationalScope
 * manual di OutsideAttendanceApprovalController (pimpinan_kantor, 1 tahap,
 * office exact — pola sama seed_shift_swap_workflow.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        $definitionId = (string) Str::uuid();

        DB::table('wf_definitions')->insert([
            'id' => $definitionId,
            'document_type' => 'outside_attendance_request',
            'name' => 'Persetujuan Absen Luar Kantor',
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
            'name' => 'Keputusan Pimpinan Kantor',
            'approval_pattern' => 'individual',
            'quorum' => null,
            'require_unanimous' => false,
            'sla_days' => 3,
            'reminder_days_before' => json_encode([1]),
            'allow_delegation' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    public function down(): void
    {
        $definitionId = DB::table('wf_definitions')->where('document_type', 'outside_attendance_request')->value('id');

        if ($definitionId !== null) {
            DB::table('wf_steps')->where('definition_id', $definitionId)->delete();
            DB::table('wf_definitions')->where('id', $definitionId)->delete();
        }
    }
};
