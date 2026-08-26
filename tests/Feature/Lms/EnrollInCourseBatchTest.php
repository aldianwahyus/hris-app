<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Modules\Lms\Application\EnrollInCourseBatch;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class EnrollInCourseBatchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pendaftaran_berhasil_dan_terhubung_ke_workflow_serta_audit(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $batchId = $this->seedBatch();

        $enrollmentNumber = $this->enroll()->handle($employeeId, $batchId, $this->actorFor($employeeId));

        $row = DB::table('lms_enrollments')->where('enrollment_number', $enrollmentNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertSame($batchId, $row->batch_id);
        $this->assertNotNull($row->wf_instance_id);

        $instance = DB::table('wf_instances')->where('id', $row->wf_instance_id)->first();
        $this->assertSame('lms_enrollment', $instance->document_type);

        $audit = DB::table('aud_change_logs')->where('auditable_id', $row->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('submitted', $audit->action);
    }

    public function test_batch_tidak_scheduled_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $batchId = $this->seedBatch(status: 'cancelled');

        $this->expectException(InvalidArgumentException::class);

        $this->enroll()->handle($employeeId, $batchId, $this->actorFor($employeeId));
    }

    public function test_lewat_batas_pendaftaran_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $batchId = $this->seedBatch(registrationDeadline: '-1 day');

        $this->expectException(InvalidArgumentException::class);

        $this->enroll()->handle($employeeId, $batchId, $this->actorFor($employeeId));
    }

    public function test_kuota_penuh_ditolak(): void
    {
        $batchId = $this->seedBatch(capacity: 1);
        $this->enroll()->handle($this->employeeId('2018.03.0142'), $batchId, $this->actorFor($this->employeeId('2018.03.0142')));

        $keduaId = $this->employeeId('2015.07.0088');

        $this->expectException(InvalidArgumentException::class);

        $this->enroll()->handle($keduaId, $batchId, $this->actorFor($keduaId));
    }

    public function test_sudah_terdaftar_aktif_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $batchId = $this->seedBatch();

        $this->enroll()->handle($employeeId, $batchId, $this->actorFor($employeeId));

        $this->expectException(InvalidArgumentException::class);

        $this->enroll()->handle($employeeId, $batchId, $this->actorFor($employeeId));
    }

    private function enroll(): EnrollInCourseBatch
    {
        return app(EnrollInCourseBatch::class);
    }

    private function seedBatch(
        string $status = 'scheduled',
        ?int $capacity = null,
        ?string $registrationDeadline = null,
    ): string {
        $courseId = (string) Uuid7::generate();

        DB::table('lms_courses')->insert([
            'id' => $courseId,
            'code' => 'UJI-'.uniqid(),
            'title' => 'Kursus Uji',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $batchId = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_course_batches')->insert([
            'id' => $batchId,
            'course_id' => $courseId,
            'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => $now->modify('+7 days')->format('Y-m-d'),
            'end_date' => $now->modify('+9 days')->format('Y-m-d'),
            'registration_deadline' => $registrationDeadline !== null ? $now->modify($registrationDeadline)->format('Y-m-d') : null,
            'capacity' => $capacity,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return $batchId;
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
