<?php

declare(strict_types=1);

namespace Tests\Unit\Workflow;

use App\Shared\Workflow\Domain\ApprovalDecision;
use App\Shared\Workflow\Domain\ApprovalPattern;
use App\Shared\Workflow\Domain\InstanceStatus;
use App\Shared\Workflow\Domain\WorkflowInstance;
use App\Shared\Workflow\Domain\WorkflowStep;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * Menguji ketiga pola persetujuan secara utuh, memakai definisi yang
 * sama dengan yang di-seed pada migrasi:
 *
 *   Cuti   — individual berjenjang (BPP/1087/03/64/2026)
 *   Lembur — tunggal terpusat      (KEP/1827/06/64/2026)
 *   Sanksi — komite + penetapan    (SPP Sanksi)
 */
final class WorkflowInstanceTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-13');
    }

    private function decision(string $who, bool $approved): ApprovalDecision
    {
        return new ApprovalDecision($who, $approved, $this->now);
    }

    private function leaveStep(): WorkflowStep
    {
        return new WorkflowStep('s1', 1, 'Keputusan Pejabat Pemutus', ApprovalPattern::IndividualHierarchical, slaDays: 5);
    }

    // --- Pola 1: Cuti ---

    public function test_cuti_selesai_dengan_satu_persetujuan(): void
    {
        $cuti = new WorkflowInstance('leave_request', 'cuti-001', 'peg-siti', $this->now, [$this->leaveStep()]);

        $this->assertSame(InstanceStatus::Pending, $cuti->status());
        $this->assertTrue($cuti->stillReservesQuota());

        $cuti->decide($this->decision('bm-mataram', true), $this->now);

        $this->assertSame(InstanceStatus::Approved, $cuti->status());
        $this->assertFalse($cuti->stillReservesQuota());
    }

    public function test_persetujuan_menerbitkan_peristiwa_untuk_modul_lain(): void
    {
        $cuti = new WorkflowInstance('leave_request', 'cuti-001', 'peg-siti', $this->now, [$this->leaveStep()]);
        $cuti->decide($this->decision('bm-mataram', true), $this->now);

        $events = $cuti->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertSame('workflow.approved', $events[0]->eventName());
    }

    // --- Pola 2: Lembur (terpusat) ---

    /**
     * Skenario paling kritis pada sistem ini: pengajuan lembur yang tidak
     * diputus dalam 30 hari membuat hak bayar pegawai HANGUS — padahal
     * pekerjaan sudah dilakukan. Peristiwa ini wajib terekam agar dapat
     * dilaporkan, bukan hilang diam-diam.
     */
    public function test_lembur_kedaluwarsa_melepas_kuota_dan_mencatat_alasan(): void
    {
        $step = new WorkflowStep('s2', 1, 'Keputusan Direktur Pembina', ApprovalPattern::CentralizedSingle, slaDays: 30);
        $lembur = new WorkflowInstance('overtime_request', 'ovt-002', 'peg-dewi', $this->now, [$step]);

        $lembur->expire($this->now, 'Melewati 30 hari sejak pelaksanaan');

        $this->assertSame(InstanceStatus::Expired, $lembur->status());
        $this->assertTrue($lembur->status()->releasesQuota());

        $events = $lembur->releaseEvents();
        $this->assertSame('workflow.expired', $events[0]->eventName());
        $this->assertSame('Melewati 30 hari sejak pelaksanaan', $events[0]->payload()['reason']);
    }

    public function test_langkah_terpusat_wajib_menetapkan_sla(): void
    {
        $this->expectException(DomainException::class);

        new WorkflowStep('x', 1, 'Tanpa SLA', ApprovalPattern::CentralizedSingle);
    }

    // --- Pola 3: Sanksi (komite) ---

    public function test_sidang_komite_maju_ke_penetapan_setelah_kuorum(): void
    {
        $sidang = new WorkflowStep('s3', 1, 'Sidang KPP', ApprovalPattern::Committee, quorum: 3, slaDays: 14);
        $penetapan = new WorkflowStep('s4', 2, 'Penetapan Direksi', ApprovalPattern::IndividualHierarchical, slaDays: 7);

        $kasus = new WorkflowInstance('violation_case', 'vio-001', 'skai', $this->now, [$sidang, $penetapan]);

        $kasus->decide($this->decision('anggota-1', true), $this->now);
        $kasus->decide($this->decision('anggota-2', true), $this->now);

        $this->assertSame(1, $kasus->currentSequence(), 'Belum kuorum, masih di sidang.');

        $kasus->decide($this->decision('anggota-3', false), $this->now);

        $this->assertSame(2, $kasus->currentSequence(), 'Kuorum tercapai, maju ke penetapan.');
        $this->assertSame(InstanceStatus::Pending, $kasus->status());

        $kasus->decide($this->decision('direksi', true), $this->now);

        $this->assertSame(InstanceStatus::Approved, $kasus->status());
    }

    /**
     * Suara ganda pada sidang komite berdampak langsung pada sanksi
     * pegawai, sehingga dicegah berlapis: di Domain (uji ini) dan di
     * basis data (constraint uq_wf_votes_step_member).
     */
    public function test_anggota_komite_tidak_dapat_memilih_dua_kali(): void
    {
        $sidang = new WorkflowStep('s3', 1, 'Sidang KPP', ApprovalPattern::Committee, quorum: 3, slaDays: 14);
        $kasus = new WorkflowInstance('violation_case', 'vio-002', 'skai', $this->now, [$sidang]);

        $kasus->decide($this->decision('anggota-1', true), $this->now);

        $this->expectException(DomainException::class);
        $kasus->decide($this->decision('anggota-1', true), $this->now);
    }

    public function test_langkah_komite_wajib_menetapkan_kuorum(): void
    {
        $this->expectException(DomainException::class);

        new WorkflowStep('y', 1, 'Komite tanpa kuorum', ApprovalPattern::Committee);
    }

    // --- Penjagaan status ---

    public function test_pengajuan_yang_sudah_selesai_tidak_dapat_diputus_lagi(): void
    {
        $cuti = new WorkflowInstance('leave_request', 'cuti-002', 'peg-x', $this->now, [$this->leaveStep()]);
        $cuti->decide($this->decision('bm', true), $this->now);

        $this->expectException(DomainException::class);
        $cuti->decide($this->decision('bm-lain', true), $this->now);
    }

    public function test_pembatalan_melepas_kuota(): void
    {
        $cuti = new WorkflowInstance('leave_request', 'cuti-003', 'peg-y', $this->now, [$this->leaveStep()]);

        $cuti->cancel($this->now, 'peg-y');

        $this->assertSame(InstanceStatus::Cancelled, $cuti->status());
        $this->assertTrue($cuti->status()->releasesQuota());
    }

    // --- Rekonstitusi (Tahap 3 — penjadwal pengingat SLA) ---

    /**
     * Tahap 3 perlu memuat ulang instansi PENDING dari basis data untuk
     * memutuskan kedaluwarsa (expire()) tanpa membuat id baru — pemutus
     * yang sama, entri audit yang sama, harus tertaut ke wf_instances
     * yang sudah ada, bukan baris fantom.
     */
    public function test_reconstitute_mengembalikan_id_dan_status_yang_tersimpan(): void
    {
        $step = new WorkflowStep('s2', 1, 'Keputusan Direktur Pembina', ApprovalPattern::CentralizedSingle, slaDays: 30);

        $lembur = WorkflowInstance::reconstitute(
            id: 'wf-existing-001',
            documentType: 'overtime_request',
            documentId: 'ovt-099',
            initiatorId: 'peg-dewi',
            startedAt: $this->now,
            steps: [$step],
            status: InstanceStatus::Pending,
            currentSequence: 1,
            completedAt: null,
        );

        $this->assertSame('wf-existing-001', $lembur->id);
        $this->assertSame(InstanceStatus::Pending, $lembur->status());
        $this->assertTrue($lembur->stillReservesQuota());
    }

    public function test_instansi_hasil_reconstitute_dapat_dinyatakan_kedaluwarsa(): void
    {
        $step = new WorkflowStep('s2', 1, 'Keputusan Direktur Pembina', ApprovalPattern::CentralizedSingle, slaDays: 30);

        $lembur = WorkflowInstance::reconstitute(
            id: 'wf-existing-002',
            documentType: 'overtime_request',
            documentId: 'ovt-100',
            initiatorId: 'peg-dewi',
            startedAt: $this->now,
            steps: [$step],
            status: InstanceStatus::Pending,
            currentSequence: 1,
            completedAt: null,
        );

        $lembur->expire($this->now, 'Tenggat SLA terlewati tanpa keputusan.');

        $this->assertSame(InstanceStatus::Expired, $lembur->status());
        $this->assertSame('wf-existing-002', $lembur->releaseEvents()[0]->payload()['instance_id']);
    }
}
