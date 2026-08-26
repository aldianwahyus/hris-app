<?php

declare(strict_types=1);

namespace Tests\Unit\Workflow;

use App\Shared\Workflow\Domain\ApprovalDecision;
use App\Shared\Workflow\Domain\StepStatus;
use App\Shared\Workflow\Domain\Strategy\CommitteeStrategy;
use App\Shared\Workflow\Domain\Strategy\SingleApproverStrategy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ApprovalStrategyTest extends TestCase
{
    private function decision(string $who, bool $approved): ApprovalDecision
    {
        return new ApprovalDecision($who, $approved, new DateTimeImmutable());
    }

    // --- Pola tunggal: Cuti (berjenjang) & Lembur (terpusat) ---

    public function test_langkah_menunggu_bila_belum_ada_keputusan(): void
    {
        $this->assertSame(StepStatus::Pending, (new SingleApproverStrategy())->resolve([]));
    }

    public function test_satu_persetujuan_menyelesaikan_langkah(): void
    {
        $strategy = new SingleApproverStrategy();

        $this->assertSame(
            StepStatus::Approved,
            $strategy->resolve([$this->decision('direktur-bidang', true)])
        );
    }

    public function test_satu_penolakan_menggugurkan_langkah(): void
    {
        $strategy = new SingleApproverStrategy();

        $this->assertSame(
            StepStatus::Rejected,
            $strategy->resolve([$this->decision('direktur-pembina', false)])
        );
    }

    // --- Pola komite: KPP / KHC pada modul Sanksi ---

    public function test_komite_menunggu_hingga_kuorum_terpenuhi(): void
    {
        $strategy = new CommitteeStrategy(quorum: 3);

        $this->assertSame(StepStatus::Pending, $strategy->resolve([
            $this->decision('anggota-1', true),
            $this->decision('anggota-2', true),
        ]));
    }

    public function test_komite_memutus_dengan_suara_terbanyak_setelah_kuorum(): void
    {
        $strategy = new CommitteeStrategy(quorum: 3);

        $this->assertSame(StepStatus::Approved, $strategy->resolve([
            $this->decision('anggota-1', true),
            $this->decision('anggota-2', true),
            $this->decision('anggota-3', false),
        ]));
    }

    /**
     * Seri diperlakukan sebagai penolakan. Pada konteks sanksi pegawai,
     * ketiadaan mayoritas yang mendukung berarti usulan tidak dapat
     * dilanjutkan — memihak pegawai saat komite terbelah.
     */
    public function test_suara_seri_diperlakukan_sebagai_penolakan(): void
    {
        $strategy = new CommitteeStrategy(quorum: 4);

        $this->assertSame(StepStatus::Rejected, $strategy->resolve([
            $this->decision('anggota-1', true),
            $this->decision('anggota-2', true),
            $this->decision('anggota-3', false),
            $this->decision('anggota-4', false),
        ]));
    }

    public function test_komite_bulat_gugur_seketika_bila_ada_penolakan(): void
    {
        $strategy = new CommitteeStrategy(quorum: 3, requireUnanimous: true);

        $decisions = [
            $this->decision('anggota-1', true),
            $this->decision('anggota-2', false),
        ];

        $this->assertSame(StepStatus::Rejected, $strategy->resolve($decisions));
        $this->assertFalse($strategy->needsMoreDecisions($decisions));
    }
}
