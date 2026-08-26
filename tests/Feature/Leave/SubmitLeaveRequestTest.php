<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Domain\Uuid7;
use App\Modules\Leave\Application\SubmitLeaveRequest;
use App\Modules\Leave\Domain\FirstLeaveMustBeBlock;
use App\Modules\Leave\Domain\InsufficientLeaveBalance;
use App\Modules\Leave\Domain\LeaveType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tahap 2 — Pengajuan dari Layar: aturan Domain untuk Cuti Tahunan
 * (urutan konsumsi kantong, blok minimal pengambilan pertama), dan
 * keterhubungannya dengan Workflow Engine + audit trail.
 */
final class SubmitLeaveRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pengambilan_pertama_kurang_dari_5_hari_ditolak(): void
    {
        $employeeId = $this->employeeId('2015.07.0088'); // Ahmad Fauzi — belum pernah cuti tahun ini

        $this->expectException(FirstLeaveMustBeBlock::class);

        $this->submit()->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-03'), // 3 hari
            reason: null,
            actor: $this->actorFor($employeeId),
        );
    }

    public function test_pengambilan_pertama_5_hari_berhasil_dan_terhubung_ke_workflow_serta_audit(): void
    {
        $employeeId = $this->employeeId('2015.07.0088');

        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 HARI KERJA murni
        // (melompati akhir pekan 09-05/09-06) — memenuhi blok minimal 5
        // hari tanpa menyentuh pengecualian akhir pekan itu sendiri.
        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: 'Uji coba',
            actor: $this->actorFor($employeeId),
        );

        $row = DB::table('leave_requests')->where('request_number', $requestNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertNotNull($row->wf_instance_id, 'Pengajuan harus terhubung ke instansi Workflow Engine.');

        $instance = DB::table('wf_instances')->where('id', $row->wf_instance_id)->first();
        $this->assertSame('leave_request', $instance->document_type);
        $this->assertSame('pending', $instance->status);
        $this->assertSame($employeeId, $instance->initiator_id);

        $step = DB::table('wf_instance_steps')->where('instance_id', $instance->id)->first();
        $this->assertNotNull($step, 'Langkah berjalan pertama harus tercatat, termasuk tenggat SLA.');
        $this->assertNotNull($step->sla_due_at);

        $audit = DB::table('aud_change_logs')->where('auditable_id', $row->id)->first();
        $this->assertNotNull($audit, 'Setiap pengajuan wajib tercatat pada audit trail.');
        $this->assertSame('submitted', $audit->action);
        $this->assertSame($employeeId, $audit->actor_id);
    }

    public function test_pengambilan_kedua_pada_tahun_yang_sama_tidak_terikat_batas_5_hari(): void
    {
        // Dewi sudah punya pengajuan CT berstatus pending tahun ini (data contoh Wave 1).
        $employeeId = $this->employeeId('2019.09.0177');

        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-10-01'),
            endDate: new DateTimeImmutable('2026-10-01'), // 1 hari — di bawah 5, tapi bukan pengambilan pertama
            reason: null,
            actor: $this->actorFor($employeeId),
        );

        $this->assertNotNull(DB::table('leave_requests')->where('request_number', $requestNumber)->first());
    }

    public function test_saldo_tidak_cukup_ditolak(): void
    {
        $employeeId = $this->employeeId('2017.11.0119'); // Hendra — kuota 12+2, belum dipakai

        $this->expectException(InsufficientLeaveBalance::class);

        // 2026-09-01 s.d. 2026-09-22 = 16 HARI KERJA (setelah akhir pekan
        // dikecualikan) > 14 total tersedia — margin sengaja dilebarkan
        // dari rentang kalender mentah supaya tetap melebihi saldo
        // walau 4 akhir pekan di dalamnya sudah tidak dihitung.
        $this->submit()->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-22'),
            reason: null,
            actor: $this->actorFor($employeeId),
        );
    }

    public function test_konsumsi_meluap_ke_kantong_sisa_tahun_lalu_sesuai_urutan(): void
    {
        $employeeId = $this->employeeId('2017.11.0119');

        // Sisakan hanya 4 hari di jatah tahun berjalan, 2 hari di sisa tahun lalu.
        DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->update(['used_days' => 8]); // quota 12 - 8 = sisa 4

        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 HARI KERJA murni
        // (sama seperti test blok minimal pertama di atas): 4 dari tahun
        // berjalan, 1 dari sisa tahun lalu.
        $this->submit()->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: null,
            actor: $this->actorFor($employeeId),
        );

        $buckets = DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)
            ->pluck('used_days', 'bucket_type');

        $this->assertEquals(12.0, (float) $buckets['current_year'], 'Jatah tahun berjalan wajib habis dulu.');
        $this->assertEquals(1.0, (float) $buckets['carry_forward'], 'Sisa 1 hari baru diambilkan dari sisa tahun lalu.');
    }

    public function test_hari_libur_nasional_dikecualikan_dari_total_hari_cuti(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti — belum pernah cuti tahun ini

        // 2026-09-01 (Selasa) s.d. 2026-09-14 (Senin): 14 hari kalender,
        // 10 hari kerja (akhir pekan 09-05/06 & 09-12/13 dikecualikan).
        // Tambah 1 hari libur nasional di tengah (09-03, Kamis) — total
        // hari cuti harus 9, BUKAN 10 (hari kerja tanpa libur) atau 14
        // (kalender mentah).
        DB::table('cfg_national_holidays')->insert([
            'id' => (string) Uuid7::generate(),
            'holiday_date' => '2026-09-03',
            'name' => 'Uji Libur Nasional',
            'is_national' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            leaveType: LeaveType::CutiTahunan,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-14'),
            reason: null,
            actor: $this->actorFor($employeeId),
        );

        $row = DB::table('leave_requests')->where('request_number', $requestNumber)->first();
        $this->assertEquals(9.0, (float) $row->total_days);
    }

    private function submit(): SubmitLeaveRequest
    {
        return app(SubmitLeaveRequest::class);
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
