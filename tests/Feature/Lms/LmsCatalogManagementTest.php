<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Katalog kursus+jadwal — hr_admin/hr_approver/system_admin lewat
 * permission dinamis `lms-catalog.manage` (BUKAN role:xxx hardcode) —
 * bukti sistem RBAC dinamis (lihat RoleFeatureMapController) dipakai
 * untuk fitur BARU, bukan cuma migrasi fitur lama.
 */
final class LmsCatalogManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_menambah_kursus(): void
    {
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->post('/admin/pelatihan', [
            'code' => 'K3-01',
            'title' => 'Keselamatan Kerja Dasar',
        ]);

        $response->assertRedirect(route('lms.admin.courses.index'));
        $course = DB::table('lms_courses')->where('code', 'K3-01')->first();
        $this->assertNotNull($course);

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'lms_course')->where('auditable_id', $course->id)
            ->where('action', 'created')->first();
        $this->assertNotNull($audit);
    }

    public function test_hr_approver_dapat_menambah_jadwal_kelas(): void
    {
        $courseId = $this->seedCourse();

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/admin/pelatihan/{$courseId}/jadwal", [
                'batch_code' => 'BATCH-1',
                'start_date' => now()->addDays(7)->format('Y-m-d'),
                'end_date' => now()->addDays(9)->format('Y-m-d'),
                'capacity' => 20,
            ]);

        $response->assertRedirect(route('lms.admin.courses.index'));
        $this->assertSame(1, DB::table('lms_course_batches')->where('course_id', $courseId)->where('batch_code', 'BATCH-1')->count());
    }

    public function test_system_admin_dapat_mengelola_katalog(): void
    {
        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->get('/admin/pelatihan');

        $response->assertOk();
    }

    public function test_kode_kursus_duplikat_ditolak(): void
    {
        $this->seedCourse('DUP-01');

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->post('/admin/pelatihan', [
            'code' => 'DUP-01',
            'title' => 'Kursus Lain',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('lms_courses')->where('code', 'DUP-01')->count());
    }

    public function test_kursus_dengan_jadwal_tidak_dapat_dihapus(): void
    {
        $courseId = $this->seedCourse();
        DB::table('lms_course_batches')->insert([
            'id' => (string) Uuid7::generate(),
            'course_id' => $courseId,
            'batch_code' => 'BATCH-1',
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('SYSADMIN'))->delete("/admin/pelatihan/{$courseId}");

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('lms_courses')->where('id', $courseId)->whereNotNull('deleted_at')->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/admin/pelatihan');

        $response->assertForbidden();
    }

    private function seedCourse(string $code = 'UJI-01'): string
    {
        $id = (string) Uuid7::generate();

        DB::table('lms_courses')->insert([
            'id' => $id,
            'code' => $code,
            'title' => 'Kursus Uji',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
