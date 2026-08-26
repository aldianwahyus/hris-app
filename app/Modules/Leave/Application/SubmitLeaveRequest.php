<?php

declare(strict_types=1);

namespace App\Modules\Leave\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Leave\Domain\BucketType;
use App\Modules\Leave\Domain\FirstLeaveBlockPolicy;
use App\Modules\Leave\Domain\LeaveBalanceLedger;
use App\Modules\Leave\Domain\LeaveBucket;
use App\Modules\Leave\Domain\LeaveType;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Holiday\Domain\HolidayRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan cuti dari layar ESS.
 *
 * Aturan bisnis (urutan konsumsi kantong, blok minimal pengambilan
 * pertama) ditegakkan di app/Modules/Leave/Domain — kelas ini hanya
 * mengorkestrasi: kunci baris saldo, jalankan aturan, tulis pengajuan,
 * mulai instansi Workflow Engine, catat audit trail. Seluruhnya dalam
 * SATU transaksi agar tidak ada kondisi tanggung (saldo terpotong tanpa
 * pengajuan tercatat, atau sebaliknya).
 */
final class SubmitLeaveRequest
{
    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
        private readonly ParameterResolver $parameters,
        private readonly HolidayRepository $holidays,
    ) {}

    public function handle(
        string $employeeId,
        LeaveType $leaveType,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?string $reason,
        AuditActor $actor,
    ): string {
        if (! $leaveType->consumesAnnualBalance()) {
            throw new DomainException(
                "Jenis cuti \"{$leaveType->label()}\" belum didukung pengajuan dari layar pada tahap ini."
            );
        }

        // Hari cuti dihitung sebagai HARI KERJA (mengecualikan akhir
        // pekan & hari libur nasional), BUKAN selisih tanggal kalender
        // mentah — lihat HolidayRepository::countWorkingDays().
        $totalDays = (float) $this->holidays->countWorkingDays($startDate, $endDate);
        $year = (int) $startDate->format('Y');

        return DB::transaction(function () use ($employeeId, $leaveType, $startDate, $endDate, $totalDays, $year, $reason, $actor) {
            // Kunci baris saldo SEBELUM membaca — dua pengajuan bersamaan
            // tidak boleh sama-sama lolos dari saldo yang sama.
            $bucketRows = DB::table('leave_balances')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->whereIn('bucket_type', [BucketType::CurrentYear->value, BucketType::CarryForward->value])
                ->lockForUpdate()
                ->get();

            $buckets = $bucketRows->map(fn ($row) => new LeaveBucket(
                type: BucketType::from($row->bucket_type),
                quotaDays: (float) $row->quota_days,
                usedDays: (float) $row->used_days,
                consumptionOrder: $row->consumption_order,
            ))->all();

            $plan = (new LeaveBalanceLedger($buckets))->planConsumption($totalDays);

            $isFirstTakenThisYear = ! DB::table('leave_requests')
                ->where('employee_id', $employeeId)
                ->where('leave_type', $leaveType->value)
                ->whereYear('start_date', $year)
                // Kedaluwarsa (Tahap 3 — tenggat SLA terlewati tanpa
                // keputusan) juga bukan "sudah diambil": tidak ada
                // keputusan yang benar-benar terjadi.
                ->whereNotIn('status', ['rejected', 'cancelled', 'expired'])
                ->exists();

            $minimumBlockDays = (float) $this->parameters->integer('LEAVE_BLOCK_LEAVE_MIN', AsOfDate::on($startDate));

            (new FirstLeaveBlockPolicy($minimumBlockDays))->guard($isFirstTakenThisYear, $totalDays);

            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('leave_requests')->insert([
                'id' => $requestId,
                'request_number' => $this->nextRequestNumber($leaveType, $now),
                'employee_id' => $employeeId,
                'leave_type' => $leaveType->value,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $totalDays,
                // Snapshot rencana debit ($plan, dihitung di atas) — SATU-
                // SATUNYA cara ReleaseLeaveBalance (reject) dan
                // ProcessWorkflowSla (kedaluwarsa) tahu persis kantong mana
                // dan berapa hari yang harus dikembalikan. Tanpa ini,
                // pengajuan yang ditolak/kedaluwarsa menghanguskan jatah
                // cuti pegawai secara permanen (bug yang diperbaiki di sini).
                'bucket_debits' => json_encode(array_map(
                    static fn ($debit) => ['bucket_type' => $debit->bucketType->value, 'days' => $debit->days],
                    $plan,
                )),
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            foreach ($plan as $debit) {
                DB::table('leave_balances')
                    ->where('employee_id', $employeeId)
                    ->where('year', $year)
                    ->where('bucket_type', $debit->bucketType->value)
                    ->increment('used_days', $debit->days);
            }

            $instance = $this->workflow->startFor('leave_request', $requestId, $employeeId, $now);

            DB::table('leave_requests')->where('id', $requestId)->update(['wf_instance_id' => $instance->id]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'leave_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: [
                    'leave_type' => $leaveType->value,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'total_days' => $totalDays,
                    'wf_instance_id' => $instance->id,
                ],
            ));

            return DB::table('leave_requests')->where('id', $requestId)->value('request_number');
        });
    }

    private function nextRequestNumber(LeaveType $leaveType, DateTimeImmutable $now): string
    {
        $prefix = sprintf('%s/%s/%s/', $leaveType->value, $now->format('Y'), $now->format('m'));

        $count = DB::table('leave_requests')
            ->where('request_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
