<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Application;

use App\Core\Domain\Uuid7;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\JabatanTierNotMapped;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdBudgetCalculator;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengajukan SPPD dari layar ESS — selalu atas nama pegawai yang
 * sedang masuk (ownership; tidak ada parameter employee_id di sini).
 *
 * SELURUH komponen anggaran (Uang Makan, Uang Saku, plafon Hotel/
 * Angkutan Setempat/Transportasi Tujuan) dihitung PENUH oleh sistem
 * lewat SppdBudgetCalculator — tidak ada satu pun kolom uang yang
 * diserahkan dari luar (form/klien), justru untuk mencegah diisi
 * sembarangan. Realisasi sesuai invoice adalah tahap lanjutan, belum
 * dibangun (lihat DEC-37 untuk pola serupa pada Lembur).
 */
final class SubmitSppdRequest
{
    public function __construct(
        private readonly SppdBudgetCalculator $calculator,
        private readonly AuditRepository $audit,
    ) {}

    public function handle(
        string $employeeId,
        TripCategory $tripCategory,
        string $destination,
        string $purpose,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?RadiusBand $radiusBand,
        AuditActor $actor,
    ): string {
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $totalDays = $startDate->diff($endDate)->days + 1;
        $asOf = AsOfDate::on($startDate);

        $tier = null;

        if (! $tripCategory->berbasisRadius()) {
            $tierValue = DB::table('emp_employees as e')
                ->join('md_positions as p', 'p.id', '=', 'e.position_id')
                ->where('e.id', $employeeId)
                ->value('p.sppd_jabatan_tier');

            if ($tierValue === null) {
                throw JabatanTierNotMapped::forEmployee($employeeId);
            }

            $tier = JabatanTier::from($tierValue);
        }

        $budget = $this->calculator->compute($tripCategory, $tier, $radiusBand, $totalDays, $asOf);

        return DB::transaction(function () use (
            $employeeId, $tripCategory, $tier, $radiusBand, $destination, $purpose,
            $startDate, $endDate, $totalDays, $budget, $actor
        ) {
            $now = new DateTimeImmutable;
            $requestId = (string) Uuid7::generate();

            DB::table('spd_requests')->insert([
                'id' => $requestId,
                'request_number' => $this->nextRequestNumber($now),
                'employee_id' => $employeeId,
                'trip_category' => $tripCategory->value,
                'jabatan_tier' => $tier?->value,
                'radius_band' => $radiusBand?->value,
                'destination' => $destination,
                'purpose' => $purpose,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $totalDays,
                'currency' => $budget->mataUang,
                'uang_makan_cents' => $budget->uangMakan->cents,
                'uang_saku_cents' => $budget->uangSaku->cents,
                'estimasi_hotel_cents' => $budget->hotel?->cents,
                'estimasi_angkutan_setempat_cents' => $budget->angkutanSetempat?->cents,
                'estimasi_transportasi_tujuan_cents' => $budget->transportasiTujuan?->cents,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'spd_request',
                auditableId: $requestId,
                action: AuditAction::Submitted,
                newValues: [
                    'trip_category' => $tripCategory->value,
                    'destination' => $destination,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'total_anggaran_cents' => $budget->totalKeseluruhan()->cents,
                ],
            ));

            return DB::table('spd_requests')->where('id', $requestId)->value('request_number');
        });
    }

    private function nextRequestNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('SPPD/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('spd_requests')->where('request_number', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
