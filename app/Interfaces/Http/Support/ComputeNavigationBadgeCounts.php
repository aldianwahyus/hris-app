<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Support;

use App\Interfaces\Http\Controllers\ApprovalQueueController;
use App\Interfaces\Http\Controllers\BekalCutiDisbursementController;
use App\Interfaces\Http\Controllers\DocumentRequestQueueController;
use App\Interfaces\Http\Controllers\EmployeeApprovalQueueController;
use App\Interfaces\Http\Controllers\HelpdeskQueueController;
use App\Interfaces\Http\Controllers\IzinApprovalController;
use App\Interfaces\Http\Controllers\JobRequisitionController;
use App\Interfaces\Http\Controllers\LeaveApprovalQueueController;
use App\Interfaces\Http\Controllers\LmsEnrollmentApprovalController;
use App\Interfaces\Http\Controllers\OffboardingQueueController;
use App\Interfaces\Http\Controllers\OutsideAttendanceApprovalController;
use App\Interfaces\Http\Controllers\OvertimeDisbursementController;
use App\Interfaces\Http\Controllers\PayrollApprovalController;
use App\Interfaces\Http\Controllers\ShiftSwapApprovalController;
use App\Interfaces\Http\Controllers\SppdApprovalController;
use App\Interfaces\Http\Controllers\SppdDisbursementController;
use App\Modules\Access\Contracts\CurrentActor;
use Closure;
use Throwable;

/**
 * Badge notifikasi sidebar — jumlah pending per antrean
 * persetujuan/pembayaran (di luar mekanisme pembayaran lembur itu
 * sendiri, tambahan terpisah). Sengaja di lapisan Interfaces (BUKAN
 * app/Modules/*\/Application — kelas ini murni komposisi presentasi
 * yang MEMANGGIL controller, arah ketergantungan Application→Interfaces
 * terbalik dari konvensi lapisan proyek ini).
 *
 * Memanggil method `pendingCount()` yang SUDAH ditambahkan ke tiap
 * controller antrean (query+filter SAMA persis `index()` masing-masing,
 * hanya beda operasi terakhir jadi count) — BUKAN menduplikasi logika
 * AccessPolicy/scoping di sini, supaya SATU sumber kebenaran per
 * antrean, tidak ada risiko dua implementasi diam-diam berbeda.
 *
 * Dibungkus try/catch PER ITEM — kelas ini dipanggil dari view
 * composer pada SETIAP muat halaman; satu antrean error tidak boleh
 * menjatuhkan seluruh layout.
 */
final class ComputeNavigationBadgeCounts
{
    /** @return array<string, int> nama-rute → jumlah pending (hanya yang > 0) */
    public function forActor(CurrentActor $actor): array
    {
        $roles = $actor->roles();
        $counts = [];

        $has = fn (string ...$anyOf): bool => array_intersect($anyOf, $roles) !== [];

        if ($has('atasan_langsung', 'pimpinan_kantor', 'auditor')) {
            $counts['admin.approval-queue'] = $this->safeCount(fn () => app(ApprovalQueueController::class)->pendingCount());
            $counts['admin.leave-approval-queue'] = $this->safeCount(fn () => app(LeaveApprovalQueueController::class)->pendingCount());
            $counts['admin.sppd-approval-queue'] = $this->safeCount(fn () => app(SppdApprovalController::class)->pendingCount());
        }

        if ($has('atasan_langsung', 'auditor')) {
            $counts['admin.shift-swap-queue'] = $this->safeCount(fn () => app(ShiftSwapApprovalController::class)->pendingCount());
            $counts['admin.lms-enrollment-queue'] = $this->safeCount(fn () => app(LmsEnrollmentApprovalController::class)->pendingCount());
            $counts['admin.izin-queue'] = $this->safeCount(fn () => app(IzinApprovalController::class)->pendingCount());
        }

        if ($has('pimpinan_kantor', 'auditor')) {
            $counts['admin.outside-attendance-queue'] = $this->safeCount(fn () => app(OutsideAttendanceApprovalController::class)->pendingCount());
        }

        if ($has('hr_approver')) {
            $counts['admin.employee-approval-queue'] = $this->safeCount(fn () => app(EmployeeApprovalQueueController::class)->pendingCount());
            $counts['admin.payroll-approval-queue'] = $this->safeCount(fn () => app(PayrollApprovalController::class)->pendingCount());
            $counts['admin.overtime-disbursement-queue'] = $this->safeCount(fn () => app(OvertimeDisbursementController::class)->pendingCountHc());
            $counts['admin.sppd-disbursement-queue'] = $this->safeCount(fn () => app(SppdDisbursementController::class)->pendingCountHc());
            $counts['admin.bekal-cuti-queue'] = $this->safeCount(fn () => app(BekalCutiDisbursementController::class)->pendingCountHc());
            $counts['admin.recruitment-requisition-index'] = $this->safeCount(fn () => app(JobRequisitionController::class)->pendingCount());
        }

        if ($has('hr_admin', 'hr_approver')) {
            $counts['admin.document-request-queue'] = $this->safeCount(fn () => app(DocumentRequestQueueController::class)->pendingCount());
            $counts['admin.helpdesk-queue'] = $this->safeCount(fn () => app(HelpdeskQueueController::class)->pendingCount());
            $counts['admin.offboarding-index'] = $this->safeCount(fn () => app(OffboardingQueueController::class)->pendingCount());
        }

        if ($has('hr_admin')) {
            $counts['hr.overtime-disbursement.index'] = $this->safeCount(fn () => app(OvertimeDisbursementController::class)->pendingCountBranch());
            $counts['hr.sppd-disbursement.index'] = $this->safeCount(fn () => app(SppdDisbursementController::class)->pendingCountBranch());
            $counts['hr.bekal-cuti.index'] = $this->safeCount(fn () => app(BekalCutiDisbursementController::class)->pendingCountBranch());
        }

        return array_filter($counts, fn (int $n): bool => $n > 0);
    }

    private function safeCount(Closure $compute): int
    {
        try {
            return $compute();
        } catch (Throwable) {
            return 0;
        }
    }
}
