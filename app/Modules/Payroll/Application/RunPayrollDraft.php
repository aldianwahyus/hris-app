<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application;

use App\Core\Domain\Money;
use App\Core\Domain\Uuid7;
use App\Modules\Payroll\Domain\GajiPokokCalculator;
use App\Modules\Payroll\Domain\PayrollRunAlreadyExists;
use App\Modules\Payroll\Domain\Pph21Calculator;
use App\Modules\Payroll\Domain\Pph21Golongan;
use App\Modules\Payroll\Domain\SalaryScaleRepository;
use App\Modules\Payroll\Domain\SalaryStepNotFound;
use App\Modules\Payroll\Domain\TerRateNotFound;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Admin SDM (maker) menjalankan draf payroll satu kantor untuk satu
 * periode (BPP/137/03/64/2026) — Pejabat SDM (checker) yang
 * menyetujuinya lewat DecidePayrollRun, sejalan pola maker-checker
 * yang sama dipakai data induk pegawai (§6.3).
 *
 * Imbalan Kerja, Tunjangan Jabatan/Penyesuaian, iuran, dan PPh 21
 * SEMENTARA dihitung di sini — lihat PayslipComponents untuk daftar
 * yang sengaja masih ditinggalkan menunggu Lampiran III (Tunjangan
 * Kinerja/Kemahalan), dan komentar PPh21 di bawah untuk kenapa
 * hasilnya provisional bukan final.
 */
final class RunPayrollDraft
{
    public function __construct(
        private readonly SalaryScaleRepository $salaryScale,
        private readonly ParameterResolver $parameters,
        private readonly AuditRepository $audit,
        private readonly Pph21Calculator $pph21,
    ) {}

    /**
     * @param  array<int, array{name: string, reason: string}>|null  $failedEmployees  Nilai
     *                                                                                 masukan diabaikan sepenuhnya — SELALU ditimpa di awal method.
     *
     * @param-out  array<int, array{name: string, reason: string}>  $failedEmployees  Diisi
     *   BALIK (by-reference) dengan pegawai yang DILEWATI. Opsional:
     *   pemanggil yang tidak butuh daftarnya (mis. test lama) boleh
     *   mengabaikannya sepenuhnya.
     */
    public function handle(string $officeId, DateTimeImmutable $period, AuditActor $actor, ?array &$failedEmployees = null): string
    {
        $failedEmployees = [];
        $periodStart = new DateTimeImmutable($period->format('Y-m-01'));
        $periodDate = $periodStart->format('Y-m-d');
        $asOf = AsOfDate::on($periodStart);

        return DB::transaction(function () use ($officeId, $periodDate, $asOf, $actor, &$failedEmployees) {
            $exists = DB::table('pay_payroll_runs')
                ->where('office_id', $officeId)
                ->where('period', $periodDate)
                ->exists();

            if ($exists) {
                throw PayrollRunAlreadyExists::forPeriod($periodDate);
            }

            $now = new DateTimeImmutable;
            $runId = (string) Uuid7::generate();

            DB::table('pay_payroll_runs')->insert([
                'id' => $runId,
                'office_id' => $officeId,
                'period' => $periodDate,
                'status' => 'draft',
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $calculator = new GajiPokokCalculator(
                pensionEmployeePct: $this->parameters->decimal('CONTRIB_PENSION_EMPLOYEE_PCT', $asOf),
                thtTotalPct: $this->parameters->decimal('CONTRIB_THT_TOTAL_PCT', $asOf),
                thtEmployeePct: $this->parameters->decimal('CONTRIB_THT_EMPLOYEE_PCT', $asOf),
            );

            // Pegawai tetap saja pada tahap ini — trainee/kontrak/outsource
            // punya skema kepegawaian berbeda (BPP §D.6-7) yang belum
            // dipetakan ke skala imbalan kerja yang sama. person_grade
            // NULL juga disingkirkan — akun teknis seperti Admin Sistem
            // (Role::SystemAdmin) menumpang emp_employees agar login NRP
            // berfungsi, tapi bukan pegawai SDM yang bergaji.
            $employees = DB::table('emp_employees')
                ->where('office_id', $officeId)
                ->where('employment_status', 'tetap')
                ->whereNotNull('person_grade')
                ->get([
                    'id', 'full_name', 'person_grade', 'salary_step', 'tunjangan_jabatan_cents', 'tunjangan_penyesuaian_cents',
                    'marital_status', 'tanggungan',
                ]);

            $slipCount = 0;

            foreach ($employees as $employee) {
                // Satu pegawai tanpa baris skala imbalan kerja yang valid
                // (mis. Person Grade/baris menunggu Lampiran II) TIDAK
                // BOLEH menggagalkan SELURUH transaksi per-kantor — bug
                // ditemukan lewat evaluasi PM/client, pegawai ini DILEWATI
                // (bukan menghentikan slip pegawai lain di kantor yang
                // sama), dikumpulkan ke $failedEmployees agar HC tahu
                // persis siapa dan kenapa, bukan menebak dari galat generik.
                try {
                    $imbalanKerja = $this->salaryScale->amountFor((int) $employee->person_grade, (int) $employee->salary_step, $asOf);
                } catch (SalaryStepNotFound $e) {
                    $failedEmployees[] = ['name' => $employee->full_name, 'reason' => $e->getMessage()];

                    continue;
                }

                $components = $calculator->compute(
                    $imbalanKerja,
                    Money::fromCents((int) $employee->tunjangan_jabatan_cents),
                    Money::fromCents((int) $employee->tunjangan_penyesuaian_cents),
                );

                // PPh 21 SEMENTARA (provisional, atas persetujuan eksplisit
                // pengguna) — basis Gaji Bruto di sini adalah Imbalan Kerja +
                // Tunjangan Jabatan + Penyesuaian SAJA (BUKAN takeHomePartial,
                // yang sudah dikurangi iuran — TER wajib dari bruto SEBELUM
                // potongan apa pun). Tunjangan Kinerja/Kemahalan belum
                // termasuk, jadi HARUS dikoreksi ulang nanti (lihat
                // PayslipComponents::pendingComponents()).
                $golongan = null;
                $pph21 = null;
                $takeHome = $components->takeHomePartial;

                if ($employee->marital_status !== null) {
                    $golongan = Pph21Golongan::fromStatus($employee->marital_status === 'menikah', (int) $employee->tanggungan);
                    $grossMonthly = $imbalanKerja
                        ->add($components->tunjanganJabatan)
                        ->add($components->tunjanganPenyesuaian);

                    try {
                        $pph21 = $this->pph21->compute($golongan, $grossMonthly, $asOf);
                        $takeHome = $takeHome->subtract($pph21);
                    } catch (TerRateNotFound) {
                        $golongan = null; // tercatat sebagai belum dihitung untuk pegawai ini, bukan menggagalkan seluruh run
                    }
                }

                DB::table('pay_payslips')->insert([
                    'id' => (string) Uuid7::generate(),
                    'payroll_run_id' => $runId,
                    'employee_id' => $employee->id,
                    'person_grade' => $employee->person_grade,
                    'salary_step' => $employee->salary_step,
                    'imbalan_kerja_cents' => $components->imbalanKerja->cents,
                    'tunjangan_jabatan_cents' => $components->tunjanganJabatan->cents,
                    'tunjangan_penyesuaian_cents' => $components->tunjanganPenyesuaian->cents,
                    'iuran_pensiun_pegawai_cents' => $components->iuranPensiunPegawai->cents,
                    'iuran_tht_pegawai_cents' => $components->iuranThtPegawai->cents,
                    'iuran_tht_bank_cents' => $components->iuranThtBank->cents,
                    'pph21_golongan' => $golongan?->value,
                    'pph21_cents' => $pph21?->cents,
                    'take_home_partial_cents' => $takeHome->cents,
                    'pending_components' => json_encode($components->pendingComponents($pph21 !== null)),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);

                $slipCount++;
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'payroll_run',
                auditableId: $runId,
                action: AuditAction::Submitted,
                newValues: ['office_id' => $officeId, 'period' => $periodDate, 'jumlah_slip' => $slipCount],
            ));

            return $runId;
        });
    }
}
