<?php

declare(strict_types=1);

namespace Tests\Feature\Shift;

use App\Core\Domain\Uuid7;
use App\Modules\Shift\Application\SubmitShiftSwapRequest;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class SubmitShiftSwapRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pengajuan_berhasil_dan_terhubung_ke_workflow_serta_audit(): void
    {
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');
        $pagi = $this->seedPattern('PAGI');
        $siang = $this->seedPattern('SIANG');
        $this->assignShift($ahmadId, $pagi, '2020-01-01');
        $this->assignShift($sitiId, $siang, '2020-01-01');

        $requestNumber = $this->submit()->handle(
            requestingEmployeeId: $ahmadId,
            counterpartEmployeeId: $sitiId,
            swapDate: new DateTimeImmutable('+3 days'),
            reason: 'Uji coba',
            actor: $this->actorFor($ahmadId),
        );

        $row = DB::table('shf_swap_requests')->where('request_number', $requestNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertSame($pagi, $row->requesting_original_pattern_id);
        $this->assertSame($siang, $row->counterpart_original_pattern_id);
        $this->assertNotNull($row->wf_instance_id);

        $instance = DB::table('wf_instances')->where('id', $row->wf_instance_id)->first();
        $this->assertSame('shift_swap_request', $instance->document_type);

        $audit = DB::table('aud_change_logs')->where('auditable_id', $row->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('submitted', $audit->action);
    }

    public function test_tukar_dengan_diri_sendiri_ditolak(): void
    {
        $ahmadId = $this->employeeId('2015.07.0088');

        $this->expectException(InvalidArgumentException::class);

        $this->submit()->handle(
            requestingEmployeeId: $ahmadId,
            counterpartEmployeeId: $ahmadId,
            swapDate: new DateTimeImmutable('+3 days'),
            reason: null,
            actor: $this->actorFor($ahmadId),
        );
    }

    public function test_tanggal_masa_lalu_ditolak(): void
    {
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');

        $this->expectException(InvalidArgumentException::class);

        $this->submit()->handle(
            requestingEmployeeId: $ahmadId,
            counterpartEmployeeId: $sitiId,
            swapDate: new DateTimeImmutable('-3 days'),
            reason: null,
            actor: $this->actorFor($ahmadId),
        );
    }

    public function test_pemohon_tanpa_penugasan_shift_ditolak(): void
    {
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');
        $this->assignShift($sitiId, $this->seedPattern('SIANG'), '2020-01-01');
        // Ahmad SENGAJA tidak ditugaskan shift apa pun.

        $this->expectException(InvalidArgumentException::class);

        $this->submit()->handle(
            requestingEmployeeId: $ahmadId,
            counterpartEmployeeId: $sitiId,
            swapDate: new DateTimeImmutable('+3 days'),
            reason: null,
            actor: $this->actorFor($ahmadId),
        );
    }

    public function test_rekan_tanpa_penugasan_shift_ditolak(): void
    {
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');
        $this->assignShift($ahmadId, $this->seedPattern('PAGI'), '2020-01-01');
        // Siti SENGAJA tidak ditugaskan shift apa pun.

        $this->expectException(InvalidArgumentException::class);

        $this->submit()->handle(
            requestingEmployeeId: $ahmadId,
            counterpartEmployeeId: $sitiId,
            swapDate: new DateTimeImmutable('+3 days'),
            reason: null,
            actor: $this->actorFor($ahmadId),
        );
    }

    private function submit(): SubmitShiftSwapRequest
    {
        return app(SubmitShiftSwapRequest::class);
    }

    private function seedPattern(string $code): string
    {
        $id = (string) Uuid7::generate();

        DB::table('shf_shift_patterns')->insert([
            'id' => $id,
            'code' => $code.'-'.uniqid(),
            'name' => 'Shift '.$code,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function assignShift(string $employeeId, string $patternId, string $effectiveFrom): void
    {
        DB::table('shf_employee_assignments')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'shift_pattern_id' => $patternId,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
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
