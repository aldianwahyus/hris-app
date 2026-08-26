<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Infrastructure;

use App\Core\Domain\Uuid7;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use App\Shared\Workflow\Domain\ApprovalPattern;
use App\Shared\Workflow\Domain\InstanceStatus;
use App\Shared\Workflow\Domain\NoActiveWorkflowDefinition;
use App\Shared\Workflow\Domain\WorkflowInstance;
use App\Shared\Workflow\Domain\WorkflowStep;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Memakai query builder, bukan Eloquent Model — sejalan dengan
 * EloquentAuditRepository: wf_instances/wf_instance_steps dimutasi lewat
 * transisi status yang ditegakkan Domain (WorkflowInstance::decide()/
 * expire()), bukan lewat save() bebas milik Eloquent.
 */
final class EloquentWorkflowInstanceRepository implements WorkflowInstanceRepository
{
    public function startFor(
        string $documentType,
        string $documentId,
        string $initiatorId,
        DateTimeImmutable $now,
        ?DateTimeImmutable $slaAnchor = null,
    ): WorkflowInstance {
        $definition = $this->findActiveDefinition($documentType, $now);

        if ($definition === null) {
            throw NoActiveWorkflowDefinition::forDocumentType($documentType);
        }

        $steps = $this->loadSteps($definition->id);

        $instance = new WorkflowInstance($documentType, $documentId, $initiatorId, $now, $steps);

        DB::table('wf_instances')->insert([
            'id' => $instance->id,
            'definition_id' => $definition->id,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'initiator_id' => $initiatorId,
            'status' => $instance->status()->value,
            'current_sequence' => $instance->currentSequence(),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $currentStep = $instance->currentStep();

        DB::table('wf_instance_steps')->insert([
            'id' => (string) Uuid7::generate(),
            'instance_id' => $instance->id,
            'step_id' => $currentStep->id,
            'sequence' => $currentStep->sequence,
            'status' => 'pending',
            'started_at' => $now,
            'sla_due_at' => $currentStep->slaDays === null
                ? null
                : ($slaAnchor ?? $now)->modify("+{$currentStep->slaDays} days"),
            'quorum_required' => $currentStep->quorum,
            'votes_received' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return $instance;
    }

    public function findPending(string $instanceId): ?WorkflowInstance
    {
        $row = DB::table('wf_instances')
            ->where('id', $instanceId)
            ->where('status', InstanceStatus::Pending->value)
            ->first();

        if ($row === null) {
            return null;
        }

        $steps = $this->loadSteps($row->definition_id);

        return WorkflowInstance::reconstitute(
            id: $row->id,
            documentType: $row->document_type,
            documentId: $row->document_id,
            initiatorId: $row->initiator_id,
            startedAt: new DateTimeImmutable($row->started_at),
            steps: $steps,
            status: InstanceStatus::from($row->status),
            currentSequence: $row->current_sequence,
            completedAt: $row->completed_at === null ? null : new DateTimeImmutable($row->completed_at),
        );
    }

    public function save(WorkflowInstance $instance): void
    {
        $now = new DateTimeImmutable;

        DB::table('wf_instances')->where('id', $instance->id)->update([
            'status' => $instance->status()->value,
            'current_sequence' => $instance->currentSequence(),
            'completed_at' => $instance->completedAt(),
            'updated_at' => $now,
        ]);

        if ($instance->status()->isFinal()) {
            // Instansi Tahap 2/3 satu langkah: langkah berjalan pada
            // saat transisi finalnya adalah currentSequence() itu sendiri.
            DB::table('wf_instance_steps')
                ->where('instance_id', $instance->id)
                ->where('sequence', $instance->currentSequence())
                ->update([
                    'status' => $instance->status()->value,
                    'completed_at' => $instance->completedAt(),
                    'updated_at' => $now,
                ]);
        }
    }

    private function findActiveDefinition(string $documentType, DateTimeImmutable $now): ?stdClass
    {
        $today = $now->format('Y-m-d');

        return DB::table('wf_definitions')
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderByDesc('effective_from')
            ->first();
    }

    /** @return array<int, WorkflowStep> */
    private function loadSteps(string $definitionId): array
    {
        return DB::table('wf_steps')
            ->where('definition_id', $definitionId)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($row) => new WorkflowStep(
                id: $row->id,
                sequence: $row->sequence,
                name: $row->name,
                pattern: ApprovalPattern::from($row->approval_pattern),
                quorum: $row->quorum,
                requireUnanimous: (bool) $row->require_unanimous,
                slaDays: $row->sla_days,
                reminderDaysBefore: $row->reminder_days_before === null
                    ? [7, 3]
                    : json_decode((string) $row->reminder_days_before, true),
            ))
            ->all();
    }
}
