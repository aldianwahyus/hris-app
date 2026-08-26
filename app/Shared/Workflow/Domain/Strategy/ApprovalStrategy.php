<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain\Strategy;

use App\Shared\Workflow\Domain\ApprovalDecision;
use App\Shared\Workflow\Domain\StepStatus;

/**
 * Kontrak strategi persetujuan.
 *
 * Setiap pola (individual / terpusat / komite) menentukan sendiri
 * kapan sebuah langkah dianggap selesai. Dengan memisahkannya sebagai
 * strategi, penambahan pola baru tidak menyentuh pola yang sudah ada.
 */
interface ApprovalStrategy
{
    /**
     * Menghitung status langkah setelah keputusan terbaru masuk.
     *
     * @param array<int, ApprovalDecision> $decisions
     */
    public function resolve(array $decisions): StepStatus;

    /** Apakah langkah masih menunggu keputusan tambahan. */
    public function needsMoreDecisions(array $decisions): bool;
}
