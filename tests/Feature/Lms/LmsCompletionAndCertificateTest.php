<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pencatatan kelulusan (RecordCourseCompletion) — bagian dari
 * "terintegrasi langsung ke Data Kepegawaian": lulus → certificate_number
 * terisi + tulis-turun ke emp_trainings/emp_certifications + sertifikat
 * PDF dapat diunduh (lingkup SELF, pola sama PayslipController::download()).
 */
final class LmsCompletionAndCertificateTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Regresi: kolom "Kuota" di halaman Kelola Pelatihan menghitung
     * pending+approved (LmsCourseController::index()), TAPI roster
     * peserta dulu HANYA menampilkan approved+rejected — pendaftar yang
     * belum diputuskan Atasan Langsung ikut menghitung kuota tapi tidak
     * pernah terlihat di halaman "Peserta". Lihat LmsCourseBatchController::roster().
     */
    public function test_pendaftar_berstatus_pending_tetap_tampil_di_roster_peserta(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $enrollmentId = $this->insertEnrollment($employeeId, 'pending');
        $batchId = DB::table('lms_enrollments')->where('id', $enrollmentId)->value('batch_id');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))
            ->get("/admin/pelatihan/jadwal/{$batchId}/peserta");

        $response->assertOk();
        $response->assertSeeText('Siti Rahmawati');
        $response->assertSeeText('pending');
    }

    public function test_hc_mencatat_lulus_mengisi_sertifikat_dan_menulis_turun_ke_cv(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $enrollmentId = $this->insertEnrollment($employeeId, 'approved');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302')) // hr_admin
            ->post("/admin/pelatihan/pendaftaran/{$enrollmentId}/kelulusan", [
                'completion_status' => 'lulus',
                'score' => '88.5',
            ]);

        $response->assertRedirect();

        $enrollment = DB::table('lms_enrollments')->where('id', $enrollmentId)->first();
        $this->assertSame('lulus', $enrollment->completion_status);
        $this->assertNotNull($enrollment->certificate_number);
        $this->assertEquals(88.5, $enrollment->score);

        $this->assertSame(1, DB::table('emp_trainings')->where('employee_id', $employeeId)->where('training_name', 'Kursus Uji')->count());
        $certification = DB::table('emp_certifications')->where('employee_id', $employeeId)->where('certification_name', 'Kursus Uji')->first();
        $this->assertNotNull($certification);
        $this->assertSame($enrollment->certificate_number, $certification->certificate_number);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'lms_enrollment')->where('auditable_id', $enrollmentId)
            ->where('action', 'updated')->first();
        $this->assertNotNull($audit);
    }

    public function test_kelulusan_hanya_bisa_dicatat_untuk_pendaftaran_yang_disetujui(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $enrollmentId = $this->insertEnrollment($employeeId, 'pending');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/admin/pelatihan/pendaftaran/{$enrollmentId}/kelulusan", [
                'completion_status' => 'lulus',
            ]);

        $response->assertSessionHas('gagal');
        $this->assertNull(DB::table('lms_enrollments')->where('id', $enrollmentId)->value('completion_status'));
    }

    public function test_pemilik_dapat_mengunduh_sertifikat_pdf_setelah_lulus(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->insertEnrollment($siti->employee_id, 'approved');

        $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/admin/pelatihan/pendaftaran/{$enrollmentId}/kelulusan", ['completion_status' => 'lulus', 'score' => '90']);

        $response = $this->actingAs($siti)->get("/pelatihan/sertifikat/{$enrollmentId}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pegawai_lain_tidak_dapat_mengunduh_sertifikat_yang_bukan_miliknya(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $hendra = $this->userWithNrp('2017.11.0119');
        $enrollmentId = $this->insertEnrollment($siti->employee_id, 'approved');

        $this->actingAs($this->userWithNrp('2021.05.0302'))
            ->post("/admin/pelatihan/pendaftaran/{$enrollmentId}/kelulusan", ['completion_status' => 'lulus']);

        $response = $this->actingAs($hendra)->get("/pelatihan/sertifikat/{$enrollmentId}");

        $response->assertNotFound();
    }

    public function test_belum_lulus_tidak_dapat_mengunduh_sertifikat(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $enrollmentId = $this->insertEnrollment($siti->employee_id, 'approved');

        $response = $this->actingAs($siti)->get("/pelatihan/sertifikat/{$enrollmentId}");

        $response->assertNotFound();
    }

    private function insertEnrollment(string $employeeId, string $status): string
    {
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
        DB::table('lms_course_batches')->insert([
            'id' => $batchId,
            'course_id' => $courseId,
            'batch_code' => 'BATCH-'.uniqid(),
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $id = (string) Uuid7::generate();

        DB::table('lms_enrollments')->insert([
            'id' => $id,
            'enrollment_number' => 'PLT/TEST/'.uniqid(),
            'batch_id' => $batchId,
            'employee_id' => $employeeId,
            'status' => $status,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
