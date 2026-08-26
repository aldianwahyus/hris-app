<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Absen Luar Kantor — 1 TAHAP, Pimpinan Kantor SAJA (office exact),
 * BUKAN lewat GeofencePolicy. Pola sama ShiftSwapApprovalQueueTest/
 * SppdApprovalScopeTest untuk fixture NRP kantor.
 */
final class OutsideAttendanceRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_dapat_mengajukan_absen_luar_kantor(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142')) // Siti, KC Mataram
            ->post('/absensi/luar-kantor/ajukan', [
                'work_date' => now()->subDay()->format('Y-m-d'),
                'reason' => 'Survei nasabah di lapangan.',
            ]);

        $response->assertRedirect(route('attendance.outside.create'));
        $response->assertSessionHas('sukses');

        $employeeId = $this->employeeId('2018.03.0142');
        $row = DB::table('att_outside_attendance_requests')->where('employee_id', $employeeId)->first();

        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
    }

    public function test_pengajuan_ganda_tanggal_sama_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $workDate = now()->subDays(2)->format('Y-m-d');
        $this->insertOutsideRequest($employeeId, $workDate);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/absensi/luar-kantor/ajukan', ['work_date' => $workDate, 'reason' => 'Lagi.']);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('att_outside_attendance_requests')->where('employee_id', $employeeId)->count());
    }

    public function test_pimpinan_kantor_menyetujui_meng_upsert_absensi_sebagai_hadir(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram
        $workDate = now()->subDay()->format('Y-m-d');
        $requestId = $this->insertOutsideRequest($employeeId, $workDate);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, pimpinan_kantor KC Mataram
            ->post("/persetujuan/absensi-luar-kantor/{$requestId}/setujui");

        $response->assertRedirect(route('admin.outside-attendance-queue'));
        $this->assertSame('approved', DB::table('att_outside_attendance_requests')->where('id', $requestId)->value('status'));

        $record = DB::table('att_attendance_records')
            ->where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('hadir', $record->status);
        $this->assertSame('luar_kantor', $record->check_in_source);
        $this->assertSame('luar_kantor', $record->check_out_source);
        $this->assertNull($record->check_in_lat);
        $this->assertNull($record->check_in_lng);
    }

    public function test_pimpinan_kantor_menolak_tidak_mengubah_absensi(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $workDate = now()->subDay()->format('Y-m-d');
        $requestId = $this->insertOutsideRequest($employeeId, $workDate);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/absensi-luar-kantor/{$requestId}/tolak");

        $response->assertRedirect(route('admin.outside-attendance-queue'));
        $this->assertSame('rejected', DB::table('att_outside_attendance_requests')->where('id', $requestId)->value('status'));
        $this->assertNull(DB::table('att_attendance_records')->where('employee_id', $employeeId)->where('work_date', $workDate)->first());
    }

    public function test_pimpinan_kantor_lain_tidak_dapat_melihat_atau_memutus(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram
        $requestId = $this->insertOutsideRequest($employeeId, now()->subDay()->format('Y-m-d'));

        $response = $this->actingAs($this->userWithNrp('2019.09.0177')) // Dewi, KC Selong — kantor lain
            ->post("/persetujuan/absensi-luar-kantor/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('att_outside_attendance_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pemohon_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $siti = $this->userWithNrp('2018.03.0142');
        $requestId = $this->insertOutsideRequest($siti->employee_id, now()->subDay()->format('Y-m-d'));
        $this->grantRole($siti, 'pimpinan_kantor');

        $response = $this->actingAs($siti)->post("/persetujuan/absensi-luar-kantor/{$requestId}/setujui");

        $response->assertForbidden();
    }

    public function test_peran_lain_ditolak_dari_antrean(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/persetujuan/absensi-luar-kantor');

        $response->assertForbidden();
    }

    private function insertOutsideRequest(string $employeeId, string $workDate): string
    {
        $id = (string) Uuid7::generate();

        DB::table('att_outside_attendance_requests')->insert([
            'id' => $id,
            'request_number' => 'ALK/TEST/'.uniqid(),
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'reason' => 'Uji.',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function grantRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        $alreadyHas = DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $roleId)->exists();

        if (! $alreadyHas) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        }
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
