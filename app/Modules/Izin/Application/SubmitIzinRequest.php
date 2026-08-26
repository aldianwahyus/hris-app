<?php

declare(strict_types=1);

namespace App\Modules\Izin\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Izin\Domain\IzinCategory;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Holiday\Domain\HolidayRepository;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengajukan Izin Tidak Masuk Bekerja dari layar ESS — TERPISAH dari
 * Cuti (leave_balances TIDAK disentuh sama sekali di sini, lihat
 * SubmitLeaveRequest untuk yang mengelola saldo). Langsung ke antrean
 * Atasan Langsung (1 tahap, lihat IzinApprovalController), pola SAMA
 * PERSIS SubmitShiftSwapRequest — Workflow Engine di sini HANYA untuk
 * pelacakan SLA/pengingat, bukan penentu approver.
 */
final class SubmitIzinRequest
{
    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
        private readonly HolidayRepository $holidays,
    ) {}

    public function handle(
        string $employeeId,
        IzinCategory $category,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $reason,
        ?string $attachmentPath,
        ?string $attachmentOriginalName,
        AuditActor $actor,
        bool $isAdminHc = false,
    ): string {
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        // Admin HC (hr_approver) dikecualikan — pegawai biasa hanya boleh
        // mengajukan izin untuk HARI INI (tidak back date, tidak maju),
        // dibandingkan sebagai tanggal kalender di zona kantor pegawai
        // (pola sama RequestOutsideAttendance), bukan zona default PHP.
        if (! $isAdminHc) {
            $officeTimezone = DB::table('emp_employees as e')
                ->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->where('e.id', $employeeId)
                ->value('o.timezone') ?? 'Asia/Makassar';

            $today = new DateTimeImmutable('today', new DateTimeZone($officeTimezone));

            if ($startDate->format('Y-m-d') !== $today->format('Y-m-d')) {
                throw new InvalidArgumentException('Tanggal mulai izin wajib hari ini (tidak dapat mundur/maju), kecuali untuk Admin HC.');
            }
        }

        if ($category->requiresAttachment() && $attachmentPath === null) {
            throw new InvalidArgumentException("Kategori \"{$category->label()}\" wajib menyertakan lampiran bukti.");
        }

        // Hari kerja (mengecualikan akhir pekan & hari libur nasional) —
        // pola SAMA SubmitLeaveRequest, karena akhir pekan bukan hari
        // yang "diizinkan" (pegawai memang tidak masuk kerja hari itu).
        $totalDays = (float) $this->holidays->countWorkingDays($startDate, $endDate);

        return DB::transaction(function () use ($employeeId, $category, $startDate, $endDate, $totalDays, $reason, $attachmentPath, $attachmentOriginalName, $actor) {
            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('izin_requests')->insert([
                'id' => $requestId,
                'request_number' => $this->nextRequestNumber($now),
                'employee_id' => $employeeId,
                'category' => $category->value,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $totalDays,
                'reason' => $reason,
                'attachment_path' => $attachmentPath,
                'attachment_original_name' => $attachmentOriginalName,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $instance = $this->workflow->startFor('izin_request', $requestId, $employeeId, $now);

            DB::table('izin_requests')->where('id', $requestId)->update(['wf_instance_id' => $instance->id]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'izin_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: [
                    'category' => $category->value,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'total_days' => $totalDays,
                ],
            ));

            return DB::table('izin_requests')->where('id', $requestId)->value('request_number');
        });
    }

    private function nextRequestNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('IZN/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('izin_requests')
            ->where('request_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
