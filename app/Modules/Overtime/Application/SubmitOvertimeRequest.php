<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Application;

use App\Core\Domain\Money;
use App\Core\Domain\Uuid7;
use App\Modules\Attendance\Contracts\AttendanceRepository;
use App\Modules\Overtime\Domain\DailyOvertimeLimit;
use App\Modules\Overtime\Domain\DuplicateOvertimeDate;
use App\Modules\Overtime\Domain\NoOvertimeEvidence;
use App\Modules\Overtime\Domain\OvertimeEligibility;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Modules\Overtime\Domain\OvertimeWeek;
use App\Modules\Overtime\Domain\WeeklyOvertimeQuota;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use App\Shared\Workflow\Contracts\WorkflowInstanceRepository;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Mengajukan lembur dari layar ESS.
 *
 * Jam lembur BUKAN diketik sendiri oleh pemohon — dibaca dari bukti
 * absensi sungguhan (AttendanceRepository::overtimeEvidenceOn(), DEC-37:
 * jam pulang aktual yang melewati jam kerja resmi). Tidak ada bukti —
 * pengajuan ditolak sejak awal (NoOvertimeEvidence), tidak pernah
 * masuk ke tahap plafon/tarif. Jenis lembur (Lembur Biasa/Crash
 * Program/Shift-Piket) TETAP dipilih pemohon: Crash Program adalah
 * penugasan yang disahkan terpisah oleh manajemen (lumpsum harian rata,
 * DEC-98), bukan sesuatu yang bisa disimpulkan dari jam pindai semata.
 *
 * Plafon 18 jam/minggu (DEC-31/36), batas 4 jam/hari (BR-LB-04), dan
 * kelayakan per jabatan (DEC-90) ditegakkan di app/Modules/Overtime/Domain
 * — kelas ini mengorkestrasi: baca parameter berversi, kunci baris kuota,
 * jalankan aturan, tulis pengajuan, mulai instansi Workflow Engine, catat
 * audit trail. SELECT ... FOR UPDATE dijalankan sebelum keputusan plafon
 * diambil, di dalam satu transaksi bersama sisanya, agar dua pengajuan
 * bersamaan pada minggu yang sama tidak sama-sama lolos.
 *
 * Seluruh angka kebijakan (plafon jam, jendela retroaktif, tarif per
 * kelas jabatan) dibaca dari ParameterResolver ATAS TANGGAL PELAKSANAAN
 * ($workDate) — bukan konstanta, dan bukan atas tanggal pengajuan,
 * karena SK yang berlaku mengikuti kapan pekerjaannya dilakukan.
 *
 * Tarif Crash Program (lumpsum harian) dapat dihitung penuh saat
 * pengajuan. Tarif Lembur Biasa/Shift dihitung dari hourly_rate_cents
 * × payable_hours — sah dilakukan di sini (bukan menunggu approval)
 * karena payable_hours SUDAH final saat pengajuan: DEC-37 mengharuskan
 * baik planned_hours maupun payable_hours berasal dari REALISASI
 * absensi yang sama, tidak ada tahap "rencana lalu realisasi" terpisah
 * untuk lembur.
 */
final class SubmitOvertimeRequest
{
    public function __construct(
        private readonly WorkflowInstanceRepository $workflow,
        private readonly AuditRepository $audit,
        private readonly ParameterResolver $parameters,
        private readonly AttendanceRepository $attendance,
    ) {}

    public function handle(
        string $employeeId,
        OvertimeType $overtimeType,
        DateTimeImmutable $workDate,
        AuditActor $actor,
    ): string {
        $week = OvertimeWeek::containing($workDate);
        $asOfWorkDate = AsOfDate::on($workDate);

        $position = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->where('e.id', $employeeId)
            ->select('p.eligible_overtime_regular', 'p.eligible_overtime_crash', 'p.overtime_rate_class')
            ->first();

        if ($position === null) {
            throw new RuntimeException('Data jabatan pegawai tidak ditemukan.');
        }

        (new OvertimeEligibility(
            eligibleForRegular: (bool) $position->eligible_overtime_regular,
            eligibleForCrashProgram: (bool) $position->eligible_overtime_crash,
        ))->guard($overtimeType);

        $evidence = $this->attendance->overtimeEvidenceOn($employeeId, $workDate);

        if ($evidence === null) {
            throw NoOvertimeEvidence::forDate($workDate);
        }

        $plannedHours = $evidence->hours;

        if ($overtimeType === OvertimeType::Regular) {
            $dailyCap = (float) $this->parameters->integer('OVT_DAILY_MAX_HOURS', $asOfWorkDate);
            (new DailyOvertimeLimit($dailyCap))->guard($plannedHours);
        }

        return DB::transaction(function () use ($employeeId, $overtimeType, $workDate, $plannedHours, $week, $actor, $position, $asOfWorkDate) {
            $now = new DateTimeImmutable;

            // Satu tanggal kerja hanya boleh punya SATU pengajuan lembur
            // yang "hidup" (rejected/expired dikecualikan — pegawai boleh
            // mengajukan ulang setelah ditolak/kedaluwarsa). lockForUpdate()
            // di sini menutup race SELAMA sudah ada baris untuk tanggal
            // itu; race dua pengajuan PERTAMA yang benar-benar bersamaan
            // (belum ada baris sama sekali untuk dikunci) ditutup index
            // unik parsial di migrasi (backstop, ditangkap di catch
            // QueryException bawah).
            $existingForDate = DB::table('ovt_requests')
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate->format('Y-m-d'))
                ->whereNotIn('status', ['rejected', 'expired'])
                ->lockForUpdate()
                ->exists();

            if ($existingForDate) {
                throw DuplicateOvertimeDate::forDate($workDate);
            }

            // Pastikan baris kuota ada TANPA memblokir penyisipan bersamaan
            // (ON CONFLICT DO NOTHING) — penguncian sesungguhnya terjadi
            // pada SELECT ... FOR UPDATE tepat setelah ini, ketika baris
            // dipastikan sudah ada bagi SEMUA transaksi yang bersaing.
            DB::table('ovt_weekly_quotas')->insertOrIgnore([
                'id' => (string) Uuid7::generate(),
                'employee_id' => $employeeId,
                'week_start_date' => $week->toDateString(),
                'approved_hours' => 0,
                'pending_hours' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $quotaRow = DB::table('ovt_weekly_quotas')
                ->where('employee_id', $employeeId)
                ->where('week_start_date', $week->toDateString())
                ->lockForUpdate()
                ->first();

            if ($quotaRow === null) {
                throw new RuntimeException('Baris kuota lembur mingguan tidak ditemukan setelah disisipkan.');
            }

            $weeklyCap = (float) $this->parameters->integer('OVT_WEEKLY_CAP_HOURS', $asOfWorkDate);

            $quota = new WeeklyOvertimeQuota(
                approvedHours: (float) $quotaRow->approved_hours,
                pendingHours: (float) $quotaRow->pending_hours,
                capHours: $weeklyCap,
            );

            $quota->guard($plannedHours); // WeeklyOvertimeCapExceeded bila melampaui

            [$hourlyRateCents, $amountCents] = $this->resolveTariff($overtimeType, $position, $asOfWorkDate, $plannedHours);

            $retroWindowDays = $this->parameters->integer('OVT_RETRO_WINDOW_DAYS', $asOfWorkDate);

            $requestId = (string) Uuid7::generate();

            try {
                DB::table('ovt_requests')->insert([
                    'id' => $requestId,
                    'spkl_number' => $this->nextSpklNumber($now),
                    'employee_id' => $employeeId,
                    'overtime_type' => $overtimeType->value,
                    'work_date' => $workDate->format('Y-m-d'),
                    'planned_hours' => $plannedHours,
                    // Sama dengan planned_hours: keduanya kini berasal dari
                    // REALISASI absensi (DEC-37), bukan rencana lalu realisasi
                    // terpisah — pekerjaannya sudah terjadi (dibuktikan
                    // AttendanceRepository) pada saat pengajuan ini dikirim.
                    'payable_hours' => $plannedHours,
                    'hourly_rate_cents' => $hourlyRateCents,
                    'amount_cents' => $amountCents,
                    'status' => 'pending',
                    'approval_deadline' => $workDate->modify("+{$retroWindowDays} days")->format('Y-m-d'),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);
            } catch (QueryException $e) {
                // Backstop index unik parsial (migrasi
                // ovt_requests_employee_workdate_unique) — hanya
                // tersentuh kalau race benar-benar terjadi PERSIS di
                // celah antara pengecekan lockForUpdate() di atas dan
                // INSERT ini (dua pengajuan pertama untuk tanggal itu,
                // benar-benar bersamaan). Diterjemahkan ke pesan yang
                // sama supaya pemohon tidak melihat error SQL mentah.
                if (str_contains($e->getMessage(), 'ovt_requests_employee_workdate_unique')) {
                    throw DuplicateOvertimeDate::forDate($workDate);
                }

                throw $e;
            }

            DB::table('ovt_weekly_quotas')->where('id', $quotaRow->id)->increment('pending_hours', $plannedHours);

            // DEC-94: jendela SLA dihitung sejak TANGGAL PELAKSANAAN, bukan
            // sejak pengajuan dikirim — pengajuan retroaktif tidak boleh
            // diam-diam memperpanjang tenggat sesungguhnya.
            $instance = $this->workflow->startFor(
                documentType: 'overtime_request',
                documentId: $requestId,
                initiatorId: $employeeId,
                now: $now,
                slaAnchor: $workDate,
            );

            DB::table('ovt_requests')->where('id', $requestId)->update(['wf_instance_id' => $instance->id]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'ovt_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: [
                    'overtime_type' => $overtimeType->value,
                    'work_date' => $workDate->format('Y-m-d'),
                    'planned_hours' => $plannedHours,
                    'week_start_date' => $week->toDateString(),
                    'wf_instance_id' => $instance->id,
                ],
            ));

            return DB::table('ovt_requests')->where('id', $requestId)->value('spkl_number');
        });
    }

    /** @return array{0: ?int, 1: ?int} [hourly_rate_cents, amount_cents] */
    private function resolveTariff(OvertimeType $overtimeType, stdClass $position, AsOfDate $asOf, float $payableHours): array
    {
        if ($overtimeType === OvertimeType::CrashProgram) {
            // Lumpsum harian, sama rata seluruh anggota tim (DEC-98) —
            // tidak bergantung realisasi absensi, dapat dihitung penuh
            // saat pengajuan.
            $amount = $this->parameters->money('OVT_CRASH_PER_DAY', $asOf);

            return [null, $amount->cents];
        }

        $rateCode = match ($position->overtime_rate_class) {
            'MGR_SPV_OFC' => 'OVT_RATE_MGR_SPV_OFC',
            'ASST_ADM' => 'OVT_RATE_ASST_ADM',
            'NON_ADMIN' => 'OVT_RATE_NON_ADMIN',
            default => null,
        };

        if ($rateCode === null) {
            return [null, null];
        }

        /** @var Money $rate */
        $rate = $this->parameters->money($rateCode, $asOf);

        return [$rate->cents, $rate->multiplyBy($payableHours)->cents];
    }

    private function nextSpklNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('SPKL/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('ovt_requests')
            ->where('spkl_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
