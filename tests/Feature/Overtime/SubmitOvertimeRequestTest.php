<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

use App\Core\Domain\Uuid7;
use App\Modules\Overtime\Application\SubmitOvertimeRequest;
use App\Modules\Overtime\Domain\DailyOvertimeLimitExceeded;
use App\Modules\Overtime\Domain\DuplicateOvertimeDate;
use App\Modules\Overtime\Domain\NoOvertimeEvidence;
use App\Modules\Overtime\Domain\OvertimeNotEligible;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Modules\Overtime\Domain\WeeklyOvertimeCapExceeded;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsOvertimeAttendance;
use Tests\TestCase;

/**
 * Tahap 2 — Pengajuan dari Layar: plafon 18 jam/minggu (DEC-31/DEC-32)
 * dan keterhubungannya dengan Workflow Engine + audit trail. Jam
 * lembur kini WAJIB dibuktikan lewat catatan absensi (DEC-37, lihat
 * SeedsOvertimeAttendance) — tidak lagi diketik pemohon. Uji kondisi
 * balapan yang membuktikan penguncian baris sungguhan ada di
 * OvertimeWeeklyQuotaRaceConditionTest — kelas ini menguji keputusan
 * bisnisnya.
 */
final class SubmitOvertimeRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOvertimeAttendance;

    public function test_pengajuan_dalam_plafon_berhasil_dan_terhubung_ke_workflow_serta_audit(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti — belum ada lembur minggu ini
        $workDate = new DateTimeImmutable('2026-09-02'); // Rabu
        $this->seedOvertimeAttendance($employeeId, $workDate, 3.0);

        $spklNumber = $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        $row = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertEquals(3.0, (float) $row->planned_hours, 'Jam dibaca dari absensi, bukan diketik.');
        $this->assertEquals(3.0, (float) $row->payable_hours, 'payable_hours kini terisi sejak pengajuan (DEC-37).');
        $this->assertSame('2026-10-02', $row->approval_deadline, 'Tenggat retroaktif 30 hari (DEC-94).');
        $this->assertNotNull($row->wf_instance_id);

        $instance = DB::table('wf_instances')->where('id', $row->wf_instance_id)->first();
        $this->assertSame('overtime_request', $instance->document_type);
        $this->assertSame('pending', $instance->status);

        $quota = DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->first();
        $this->assertEquals(3.0, (float) $quota->pending_hours);

        $audit = DB::table('aud_change_logs')->where('auditable_id', $row->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('submitted', $audit->action);
    }

    public function test_tanggal_tanpa_bukti_absensi_ditolak_dengan_notifikasi(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        try {
            $this->submit()->handle(
                employeeId: $employeeId,
                overtimeType: OvertimeType::Regular,
                workDate: new DateTimeImmutable('2026-09-02'), // tidak ada catatan absensi sama sekali
                actor: $this->actorFor($employeeId),
            );
            $this->fail('Seharusnya NoOvertimeEvidence dilempar.');
        } catch (NoOvertimeEvidence $e) {
            $this->assertStringContainsString('tidak ada lembur', $e->getMessage());
        }

        $this->assertSame(
            0,
            DB::table('ovt_requests')->where('employee_id', $employeeId)->where('work_date', '2026-09-02')->count()
        );
    }

    public function test_pulang_tepat_waktu_tanpa_melewati_ambang_dianggap_tidak_ada_lembur(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        // Pulang 17:10 — hanya 10 menit lewat jam kerja resmi (17:00),
        // di bawah ambang ATT_OVERTIME_MIN_MINUTES (30 menit).
        $this->seedOvertimeAttendance($employeeId, new DateTimeImmutable('2026-09-02'), 10 / 60);

        $this->expectException(NoOvertimeEvidence::class);

        $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: new DateTimeImmutable('2026-09-02'),
            actor: $this->actorFor($employeeId),
        );
    }

    public function test_pengajuan_yang_melampaui_plafon_mingguan_ditolak_dan_kuota_tidak_berubah(): void
    {
        $employeeId = $this->employeeId('2020.01.0231'); // Budi

        // Empat pengajuan 4 jam (batas harian BR-LB-04) pada hari berbeda
        // dalam minggu yang sama = 16 jam, masih dalam plafon 18 jam.
        foreach (['2026-08-31', '2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            $workDate = new DateTimeImmutable($date);
            $this->seedOvertimeAttendance($employeeId, $workDate, 4.0);

            $this->submit()->handle(
                employeeId: $employeeId,
                overtimeType: OvertimeType::Regular,
                workDate: $workDate,
                actor: $this->actorFor($employeeId),
            );
        }

        $before = DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(16.0, (float) $before);

        $workDate = new DateTimeImmutable('2026-09-04');
        $this->seedOvertimeAttendance($employeeId, $workDate, 4.0);

        try {
            // Pengajuan kelima (4 jam, dalam batas harian) mendorong total
            // minggu ini menjadi 20 jam — melampaui plafon 18 jam.
            $this->submit()->handle(
                employeeId: $employeeId,
                overtimeType: OvertimeType::Regular,
                workDate: $workDate,
                actor: $this->actorFor($employeeId),
            );
            $this->fail('Seharusnya WeeklyOvertimeCapExceeded dilempar.');
        } catch (WeeklyOvertimeCapExceeded) {
            // diharapkan
        }

        $after = DB::table('ovt_weekly_quotas')
            ->where('employee_id', $employeeId)->where('week_start_date', '2026-08-31')
            ->value('pending_hours');
        $this->assertEquals(16.0, (float) $after, 'Kuota tidak boleh berubah ketika pengajuan ditolak (transaksi dibatalkan).');

        $this->assertSame(
            0,
            DB::table('ovt_requests')->where('employee_id', $employeeId)->where('work_date', '2026-09-04')->count(),
            'Pengajuan yang ditolak tidak boleh meninggalkan baris ovt_requests.'
        );
    }

    public function test_pengajuan_melampaui_batas_harian_ditolak(): void
    {
        $employeeId = $this->employeeId('2019.09.0177'); // Dewi
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 5.0); // > 4 jam/hari (BR-LB-04)

        $this->expectException(DailyOvertimeLimitExceeded::class);

        $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );
    }

    public function test_jabatan_pimpinan_ditolak_untuk_lembur_biasa_namun_berhak_crash_program(): void
    {
        $employeeId = $this->employeeId('2015.07.0088'); // Ahmad Fauzi — Branch Manager

        try {
            // Eligibilitas jabatan diperiksa SEBELUM bukti absensi — Ahmad
            // ditolak di sini terlepas dari ada/tidaknya catatan absensi
            // pada tanggal ini.
            $this->submit()->handle(
                employeeId: $employeeId,
                overtimeType: OvertimeType::Regular,
                workDate: new DateTimeImmutable('2026-09-02'),
                actor: $this->actorFor($employeeId),
            );
            $this->fail('Branch Manager seharusnya ditolak untuk Lembur Biasa (DEC-90).');
        } catch (OvertimeNotEligible) {
            // diharapkan
        }

        $workDate = new DateTimeImmutable('2026-09-05'); // Sabtu
        $this->seedOvertimeAttendance($employeeId, $workDate, 8.0);

        $spklNumber = $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::CrashProgram,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        $row = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->first();
        $this->assertNotNull($row, 'Branch Manager tetap berhak Crash Program (DEC-90).');
        $this->assertEquals(25_000_000, $row->amount_cents, 'Tarif Crash Program flat Rp250.000 (OVT_CRASH_PER_DAY).');
    }

    public function test_tarif_lembur_biasa_dihitung_sesuai_kelas_jabatan(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti — Officer, kelas MGR_SPV_OFC
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        $spklNumber = $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        $row = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->first();
        $this->assertEquals(3_000_000, $row->hourly_rate_cents, 'Officer masuk kelas MGR_SPV_OFC — Rp30.000/jam (OVT_RATE_MGR_SPV_OFC).');
        $this->assertEquals(6_000_000, $row->amount_cents, '2 jam x Rp30.000/jam = Rp60.000 — dihitung penuh saat pengajuan karena payable_hours sudah final (DEC-37).');
    }

    public function test_pengajuan_dua_kali_di_tanggal_yang_sama_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        try {
            $this->submit()->handle(
                employeeId: $employeeId,
                overtimeType: OvertimeType::Regular,
                workDate: $workDate,
                actor: $this->actorFor($employeeId),
            );
            $this->fail('Seharusnya DuplicateOvertimeDate dilempar — sudah ada pengajuan pending untuk tanggal ini.');
        } catch (DuplicateOvertimeDate $e) {
            $this->assertStringContainsString('dua kali di tanggal yang sama', $e->getMessage());
        }

        $this->assertSame(
            1,
            DB::table('ovt_requests')->where('employee_id', $employeeId)->where('work_date', '2026-09-02')->count(),
            'Hanya satu baris ovt_requests yang boleh ada untuk pegawai+tanggal ini.'
        );
    }

    public function test_pengajuan_ulang_diperbolehkan_setelah_pengajuan_sebelumnya_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti
        $workDate = new DateTimeImmutable('2026-09-02');
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        $firstSpkl = $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        DB::table('ovt_requests')->where('spkl_number', $firstSpkl)->update(['status' => 'rejected']);

        $secondSpkl = $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        $this->assertNotSame($firstSpkl, $secondSpkl);
        $this->assertSame(
            2,
            DB::table('ovt_requests')->where('employee_id', $employeeId)->where('work_date', '2026-09-02')->count(),
            'Setelah ditolak, pegawai boleh mengajukan ulang untuk tanggal yang sama — baris lama TIDAK dihapus.'
        );
    }

    /**
     * Lapis pertahanan TERKUAT untuk larangan dua-kali-di-tanggal-sama:
     * index unik parsial di basis data (migrasi
     * ovt_requests_employee_workdate_unique) — berlaku APAPUN jalur
     * penulisannya (bukan cuma lewat SubmitOvertimeRequest::handle()),
     * termasuk kalau ada kode lain/impor data yang menulis
     * ovt_requests langsung tanpa lewat Application layer ini.
     */
    public function test_indeks_unik_basis_data_menolak_dua_baris_hidup_untuk_pegawai_dan_tanggal_yang_sama(): void
    {
        $employeeId = $this->employeeId('2017.11.0119'); // Hendra

        DB::table('ovt_requests')->insert($this->rawOvertimeRow($employeeId, '2026-09-02', 'approved'));

        $this->expectException(QueryException::class);

        DB::table('ovt_requests')->insert($this->rawOvertimeRow($employeeId, '2026-09-02', 'pending'));
    }

    public function test_indeks_unik_basis_data_mengizinkan_baris_baru_jika_sebelumnya_ditolak_atau_kedaluwarsa(): void
    {
        $employeeId = $this->employeeId('2017.11.0119'); // Hendra

        DB::table('ovt_requests')->insert($this->rawOvertimeRow($employeeId, '2026-09-02', 'rejected'));
        DB::table('ovt_requests')->insert($this->rawOvertimeRow($employeeId, '2026-09-02', 'approved'));
        DB::table('ovt_requests')->insert($this->rawOvertimeRow($employeeId, '2026-09-03', 'expired'));
        DB::table('ovt_requests')->insert($this->rawOvertimeRow($employeeId, '2026-09-03', 'approved'));

        $this->assertSame(
            4,
            DB::table('ovt_requests')->where('employee_id', $employeeId)->whereIn('work_date', ['2026-09-02', '2026-09-03'])->count()
        );
    }

    /** @return array<string, mixed> */
    private function rawOvertimeRow(string $employeeId, string $workDate, string $status): array
    {
        return [
            'id' => (string) Uuid7::generate(),
            'spkl_number' => 'SPKL/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'overtime_type' => 'regular',
            'work_date' => $workDate,
            'status' => $status,
            'approval_deadline' => now()->addDays(30)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ];
    }

    /**
     * Regresi: sebelum diperbaiki, tenggat SLA dihitung dari SAAT
     * PENGAJUAN dikirim, sehingga lembur yang diajukan retroaktif secara
     * diam-diam mendapat perpanjangan tenggat. DEC-94 eksplisit:
     * jendela dihitung sejak TANGGAL PELAKSANAAN.
     */
    public function test_tenggat_sla_dihitung_dari_tanggal_pelaksanaan_bukan_tanggal_pengajuan(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti
        $workDate = (new DateTimeImmutable)->modify('-10 days'); // pengajuan retroaktif
        $this->seedOvertimeAttendance($employeeId, $workDate, 2.0);

        $spklNumber = $this->submit()->handle(
            employeeId: $employeeId,
            overtimeType: OvertimeType::Regular,
            workDate: $workDate,
            actor: $this->actorFor($employeeId),
        );

        $request = DB::table('ovt_requests')->where('spkl_number', $spklNumber)->first();
        $step = DB::table('wf_instance_steps')->where('instance_id', $request->wf_instance_id)->first();

        $expectedFromWorkDate = $workDate->modify('+30 days')->format('Y-m-d');
        $wrongFromSubmission = (new DateTimeImmutable)->modify('+30 days')->format('Y-m-d');

        $this->assertSame(
            $expectedFromWorkDate,
            substr((string) $step->sla_due_at, 0, 10),
            'Tenggat SLA wajib dari tanggal pelaksanaan (DEC-94).'
        );
        $this->assertNotSame(
            $wrongFromSubmission,
            substr((string) $step->sla_due_at, 0, 10),
            'Tenggat SLA TIDAK boleh diam-diam memanjang mengikuti tanggal pengajuan.'
        );
    }

    private function submit(): SubmitOvertimeRequest
    {
        return app(SubmitOvertimeRequest::class);
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function actorFor(string $employeeId): AuditActor
    {
        return new AuditActor(actorId: $employeeId, actorRole: 'pegawai');
    }
}
