<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use App\Core\Domain\AggregateRoot;
use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use DomainException;

/**
 * Instansi alur persetujuan yang sedang berjalan.
 *
 * Seluruh aturan transisi status ditegakkan DI SINI, bukan di Controller.
 * Dengan begitu aturan berlaku pada semua jalur masuk — API, perintah
 * artisan, maupun proses impor.
 */
final class WorkflowInstance extends AggregateRoot
{
    private InstanceStatus $status = InstanceStatus::Pending;

    private int $currentSequence = 1;

    private ?DateTimeImmutable $completedAt = null;

    /** @var array<int, ApprovalDecision> */
    private array $currentStepDecisions = [];

    public readonly string $id;

    public function __construct(
        public readonly string $documentType,
        public readonly string $documentId,
        public readonly string $initiatorId,
        public readonly DateTimeImmutable $startedAt,
        /** @var array<int, WorkflowStep> berurutan menurut sequence */
        private readonly array $steps,
        ?string $id = null,
    ) {
        if ($steps === []) {
            throw new DomainException('Alur persetujuan harus memiliki minimal satu langkah.');
        }

        $this->id = $id ?? (string) Uuid7::generate();
    }

    /**
     * Memuat ulang instansi yang SUDAH ADA di basis data — dipakai
     * Infrastructure untuk merekonstruksi agregat sebelum memanggil
     * mutator (mis. expire()). Berbeda dari konstruktor biasa: id dan
     * status berjalan diambil dari yang tersimpan, bukan dibuat baru.
     *
     * @param  array<int, WorkflowStep>  $steps  berurutan menurut sequence
     */
    public static function reconstitute(
        string $id,
        string $documentType,
        string $documentId,
        string $initiatorId,
        DateTimeImmutable $startedAt,
        array $steps,
        InstanceStatus $status,
        int $currentSequence,
        ?DateTimeImmutable $completedAt,
    ): self {
        $instance = new self($documentType, $documentId, $initiatorId, $startedAt, $steps, $id);

        $instance->status = $status;
        $instance->currentSequence = $currentSequence;
        $instance->completedAt = $completedAt;

        return $instance;
    }

    public function status(): InstanceStatus
    {
        return $this->status;
    }

    public function currentSequence(): int
    {
        return $this->currentSequence;
    }

    public function currentStep(): WorkflowStep
    {
        foreach ($this->steps as $step) {
            if ($step->sequence === $this->currentSequence) {
                return $step;
            }
        }

        throw new DomainException("Langkah ke-{$this->currentSequence} tidak ditemukan.");
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * Mencatat satu keputusan pada langkah berjalan.
     *
     * Strategi langkah yang menentukan apakah keputusan ini sudah cukup:
     * pada pola tunggal satu keputusan langsung menyelesaikan, sedangkan
     * pada pola komite diperlukan kuorum.
     */
    public function decide(ApprovalDecision $decision, DateTimeImmutable $now): void
    {
        if ($this->status !== InstanceStatus::Pending) {
            throw new DomainException(sprintf(
                'Pengajuan berstatus "%s" tidak dapat diputus lagi.',
                $this->status->value
            ));
        }

        $step = $this->currentStep();
        $strategy = $step->strategy();

        $this->guardAgainstDuplicateVote($decision, $step);

        $this->currentStepDecisions[] = $decision;

        $result = $strategy->resolve($this->currentStepDecisions);

        if ($result === StepStatus::Pending) {
            return; // menunggu suara tambahan
        }

        if ($result === StepStatus::Rejected) {
            $this->status = InstanceStatus::Rejected;
            $this->completedAt = $now;
            $this->recordEvent(new WorkflowRejected($this->id, $this->documentType, $this->documentId, $decision->deciderId));

            return;
        }

        $this->advanceOrComplete($now);
    }

    /**
     * Menandai pengajuan kedaluwarsa karena SLA terlewati.
     *
     * Pada modul Lembur ini berarti hak bayar pegawai HANGUS — karena itu
     * peristiwa ini diterbitkan sebagai event agar dapat dicatat pada
     * audit trail dan dilaporkan kepada manajemen.
     */
    public function expire(DateTimeImmutable $now, string $reason): void
    {
        if ($this->status !== InstanceStatus::Pending) {
            throw new DomainException('Hanya pengajuan yang masih menunggu dapat dinyatakan kedaluwarsa.');
        }

        $this->status = InstanceStatus::Expired;
        $this->completedAt = $now;

        $this->recordEvent(new WorkflowExpired($this->id, $this->documentType, $this->documentId, $reason));
    }

    public function cancel(DateTimeImmutable $now, string $cancelledBy): void
    {
        if ($this->status !== InstanceStatus::Pending) {
            throw new DomainException('Hanya pengajuan yang masih menunggu dapat dibatalkan.');
        }

        $this->status = InstanceStatus::Cancelled;
        $this->completedAt = $now;

        $this->recordEvent(new WorkflowCancelled($this->id, $this->documentType, $this->documentId, $cancelledBy));
    }

    /** Apakah pengajuan masih menahan kuota pegawai (mis. plafon lembur mingguan). */
    public function stillReservesQuota(): bool
    {
        return $this->status === InstanceStatus::Pending;
    }

    private function advanceOrComplete(DateTimeImmutable $now): void
    {
        $lastSequence = max(array_map(fn (WorkflowStep $s) => $s->sequence, $this->steps));

        if ($this->currentSequence >= $lastSequence) {
            $this->status = InstanceStatus::Approved;
            $this->completedAt = $now;
            $this->recordEvent(new WorkflowApproved($this->id, $this->documentType, $this->documentId));

            return;
        }

        $this->currentSequence++;
        $this->currentStepDecisions = [];

        $this->recordEvent(new WorkflowStepAdvanced($this->id, $this->documentType, $this->documentId, $this->currentSequence));
    }

    /**
     * Satu orang satu suara per langkah. Ditegakkan di Domain maupun
     * basis data (constraint uq_wf_votes_step_member) — berlapis, karena
     * suara ganda pada sidang komite berdampak pada sanksi pegawai.
     */
    private function guardAgainstDuplicateVote(ApprovalDecision $decision, WorkflowStep $step): void
    {
        foreach ($this->currentStepDecisions as $existing) {
            if ($existing->deciderId === $decision->deciderId) {
                throw new DomainException(sprintf(
                    'Pemutus "%s" sudah memberikan keputusan pada langkah "%s".',
                    $decision->deciderId,
                    $step->name
                ));
            }
        }
    }
}
