<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Application\SubmitNewEmployeeRequest;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SYSADMIN sebagai maker KEDUA data pegawai (BANK_WIDE) — tambah
 * pegawai baru + usulkan perubahan, tetap lewat checker (hr_approver)
 * yang sama persis dipakai hr_admin. §6.3 tetap ditegakkan.
 */
final class SystemAdminEmployeeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sysadmin_melihat_daftar_pegawai_bank_wide(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysadmin)->get('/admin/sistem/pegawai');

        $response->assertOk();
        // Pegawai dari kantor BERBEDA-BEDA harus muncul semua (bukan
        // OFFICE-scoped seperti hr_admin).
        $response->assertSeeText('Siti Rahmawati'); // KC Mataram
        $response->assertSeeText('Dewi Lestari');    // KC Selong
        $response->assertSeeText('Nur Aisyah');      // Kantor Pusat
    }

    public function test_halaman_tambah_pegawai_baru_dapat_dibuka(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysadmin)->get('/admin/sistem/pegawai/tambah');

        $response->assertOk();
        $response->assertSeeText('Agama');
        $response->assertSeeText('Kontak Darurat');
        $response->assertSeeText('BPJS Ketenagakerjaan');
    }

    public function test_hr_admin_tidak_bisa_akses_rute_sysadmin_pegawai(): void
    {
        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin

        $response = $this->actingAs($rina)->get('/admin/sistem/pegawai');

        $response->assertForbidden();
    }

    public function test_sysadmin_dapat_mengusulkan_perubahan_pegawai_di_kantor_mana_pun(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $dewiEmployeeId = $this->employeeId('2019.09.0177'); // KC Selong — bukan kantor SYSADMIN
        $originalGrade = DB::table('emp_employees')->where('id', $dewiEmployeeId)->value('person_grade');

        $response = $this->actingAs($sysadmin)->post("/admin/sistem/pegawai/{$dewiEmployeeId}/ubah", [
            'person_grade' => $originalGrade + 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $pending = DB::table('emp_profile_change_requests')
            ->where('employee_id', $dewiEmployeeId)->where('status', 'pending')->first();

        $this->assertNotNull($pending);
        $this->assertSame(['person_grade' => $originalGrade + 1], json_decode($pending->proposed_changes, true));

        // hr_approver (checker) menyetujui SAMA seperti usulan hr_admin.
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $approve = $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$pending->id}/setujui");
        $approve->assertRedirect(route('admin.employee-approval-queue'));

        $this->assertSame($originalGrade + 1, DB::table('emp_employees')->where('id', $dewiEmployeeId)->value('person_grade'));
    }

    public function test_sysadmin_dapat_mengusulkan_atasan_langsung_dan_divisi_untuk_struktur_organisasi(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $ahmadId = $this->employeeId('2015.07.0088');
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($sysadmin)->post("/admin/sistem/pegawai/{$sitiId}/ubah", [
            'supervisor_id' => $ahmadId,
            'division' => 'Divisi Operasional',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $pending = DB::table('emp_profile_change_requests')
            ->where('employee_id', $sitiId)->where('status', 'pending')->first();
        $this->assertNotNull($pending);
        $proposed = json_decode($pending->proposed_changes, true);
        $this->assertSame($ahmadId, $proposed['supervisor_id']);
        $this->assertSame('Divisi Operasional', $proposed['division']);

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$pending->id}/setujui");

        $siti = DB::table('emp_employees')->where('id', $sitiId)->first();
        $this->assertSame($ahmadId, $siti->supervisor_id);
        $this->assertSame('Divisi Operasional', $siti->division);
    }

    public function test_sysadmin_dapat_mengusulkan_field_identitas_hr_baru(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $sitiId = $this->employeeId('2018.03.0142');

        $payload = [
            'agama' => 'Islam',
            'nomor_ktp' => '5271012345670001',
            'nomor_npwp' => '12.345.678.9-012.000',
            'bpjs_tenaga_kerja' => 'BPJSTK-0001',
            'bpjs_kesehatan' => 'BPJSKES-0001',
            'nomor_simpeda' => 'SIMPEDA-0001',
            'nomor_tambora_rencana' => 'TAMBORA-0001',
            'tmt_pangkat' => '2026-01-01',
        ];

        $response = $this->actingAs($sysadmin)->post("/admin/sistem/pegawai/{$sitiId}/ubah", $payload);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $pending = DB::table('emp_profile_change_requests')
            ->where('employee_id', $sitiId)->where('status', 'pending')->first();
        $this->assertNotNull($pending);
        $proposed = json_decode($pending->proposed_changes, true);
        foreach ($payload as $field => $value) {
            $this->assertSame($value, $proposed[$field]);
        }

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/pegawai/{$pending->id}/setujui");

        $employee = DB::table('emp_employees')->where('id', $sitiId)->first();
        $this->assertSame('Islam', $employee->agama);
        $this->assertSame('5271012345670001', $employee->nomor_ktp);
        $this->assertSame('2026-01-01', $employee->tmt_pangkat);
    }

    public function test_pegawai_baru_tidak_langsung_masuk_emp_employees_sampai_disetujui(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysadmin)->post('/admin/sistem/pegawai', [
            'nrp' => '2099.01.0001',
            'full_name' => 'Uji Pegawai Baru',
            'join_date' => '2026-01-01',
            'employment_status' => 'tetap',
            'permanent_date' => '2026-01-01',
            'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
            'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
        ]);

        $response->assertRedirect(route('sysadmin.employees.index'));
        $response->assertSessionHas('sukses');

        $this->assertSame(0, DB::table('emp_employees')->where('nrp', '2099.01.0001')->count());

        $pending = DB::table('emp_new_employee_requests')->where('status', 'pending')->first();
        $this->assertNotNull($pending);
        $this->assertSame('2099.01.0001', json_decode($pending->proposed_data, true)['nrp']);
    }

    public function test_hr_approver_menyetujui_membuat_pegawai_dan_akun_login(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $this->actingAs($sysadmin)->post('/admin/sistem/pegawai', [
            'nrp' => '2099.01.0002',
            'full_name' => 'Uji Pegawai Disetujui',
            'join_date' => '2026-01-01',
            'employment_status' => 'tetap',
            'permanent_date' => '2026-01-01',
            'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
            'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
        ]);

        $requestId = DB::table('emp_new_employee_requests')->where('status', 'pending')->value('id');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/pegawai-baru/{$requestId}/setujui");

        $response->assertRedirect(route('admin.employee-approval-queue'));
        $response->assertSessionHas('kata_sandi_baru');

        $employee = DB::table('emp_employees')->where('nrp', '2099.01.0002')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Uji Pegawai Disetujui', $employee->full_name);

        $user = DB::table('users')->where('employee_id', $employee->id)->first();
        $this->assertNotNull($user);

        $hasPegawaiRole = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('role_id', DB::table('roles')->where('name', 'pegawai')->value('id'))
            ->exists();
        $this->assertTrue($hasPegawaiRole);

        $this->assertSame('approved', DB::table('emp_new_employee_requests')->where('id', $requestId)->value('status'));
    }

    public function test_nrp_duplikat_ditolak_saat_pengajuan(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysadmin)->post('/admin/sistem/pegawai', [
            'nrp' => '2018.03.0142', // sudah dipakai Siti Rahmawati
            'full_name' => 'Uji Duplikat',
            'join_date' => '2026-01-01',
            'employment_status' => 'tetap',
            'permanent_date' => '2026-01-01',
            'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
            'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('emp_employees')->where('nrp', '2018.03.0142')->count());
    }

    public function test_field_hr_identitas_dan_data_pribadi_ikut_masuk_saat_tambah_pegawai_baru(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');
        $atasanId = $this->employeeId('2015.07.0088');

        $this->actingAs($sysadmin)->post('/admin/sistem/pegawai', [
            'nrp' => '2099.01.0010',
            'full_name' => 'Uji Field Lengkap',
            'join_date' => '2026-01-01',
            'employment_status' => 'tetap',
            'permanent_date' => '2026-01-01',
            'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
            'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
            'marital_status' => 'menikah',
            'tanggungan' => 2,
            'supervisor_id' => $atasanId,
            'division' => 'Divisi Operasional',
            'agama' => 'Islam',
            'nomor_ktp' => '5271012345670099',
            'nomor_npwp' => '12.345.678.9-012.099',
            'bpjs_tenaga_kerja' => 'BPJSTK-0099',
            'bpjs_kesehatan' => 'BPJSKES-0099',
            'nomor_simpeda' => 'SIMPEDA-0099',
            'nomor_tambora_rencana' => 'TAMBORA-0099',
            'tmt_pangkat' => '2026-01-01',
            'alamat' => 'Jl. Uji No. 1',
            'no_telepon' => '081200000099',
            'kontak_darurat_nama' => 'Kontak Uji',
            'kontak_darurat_hubungan' => 'Orang Tua',
            'kontak_darurat_telepon' => '081200000098',
            'pendidikan_terakhir' => 'S1',
            'pendidikan_jurusan' => 'Manajemen',
        ]);

        $requestId = DB::table('emp_new_employee_requests')->where('status', 'pending')
            ->get(['id', 'proposed_data'])
            ->first(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.01.0010')
            ->id;

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/pegawai-baru/{$requestId}/setujui")->assertRedirect();

        $employee = DB::table('emp_employees')->where('nrp', '2099.01.0010')->first();
        $this->assertNotNull($employee);
        $this->assertSame('menikah', $employee->marital_status);
        $this->assertSame(2, $employee->tanggungan);
        $this->assertSame($atasanId, $employee->supervisor_id);
        $this->assertSame('Divisi Operasional', $employee->division);
        $this->assertSame('Islam', $employee->agama);
        $this->assertSame('5271012345670099', $employee->nomor_ktp);
        $this->assertSame('12.345.678.9-012.099', $employee->nomor_npwp);
        $this->assertSame('BPJSTK-0099', $employee->bpjs_tenaga_kerja);
        $this->assertSame('BPJSKES-0099', $employee->bpjs_kesehatan);
        $this->assertSame('SIMPEDA-0099', $employee->nomor_simpeda);
        $this->assertSame('TAMBORA-0099', $employee->nomor_tambora_rencana);
        $this->assertSame('2026-01-01', $employee->tmt_pangkat);
        $this->assertSame('Jl. Uji No. 1', $employee->alamat);
        $this->assertSame('081200000099', $employee->no_telepon);
        $this->assertSame('Kontak Uji', $employee->kontak_darurat_nama);
        $this->assertSame('Orang Tua', $employee->kontak_darurat_hubungan);
        $this->assertSame('081200000098', $employee->kontak_darurat_telepon);
        $this->assertSame('S1', $employee->pendidikan_terakhir);
        $this->assertSame('Manajemen', $employee->pendidikan_jurusan);
    }

    public function test_status_tetap_tanpa_tanggal_tetap_ditolak_saat_tambah_pegawai_baru(): void
    {
        $sysadmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysadmin)->post('/admin/sistem/pegawai', [
            'nrp' => '2099.01.0011',
            'full_name' => 'Uji Tanpa Tanggal Tetap',
            'join_date' => '2026-01-01',
            'employment_status' => 'tetap',
            'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
            'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
        ]);

        $response->assertSessionHas('gagal');
        $this->assertStringContainsString('Tanggal Jadi Pegawai Tetap', session('gagal'));
        $this->assertSame(0, DB::table('emp_new_employee_requests')
            ->get(['proposed_data'])
            ->filter(fn ($row) => (json_decode($row->proposed_data, true)['nrp'] ?? null) === '2099.01.0011')
            ->count());
    }

    public function test_hr_approver_tidak_bisa_menyetujui_pengajuan_pegawai_baru_miliknya_sendiri(): void
    {
        $hrApproverEmployeeId = $this->employeeId('2014.02.0061');

        $requestId = app(SubmitNewEmployeeRequest::class)->handle(
            proposedData: [
                'nrp' => '2099.01.0003',
                'full_name' => 'Uji Swa Setuju',
                'join_date' => '2026-01-01',
                'employment_status' => 'tetap',
                'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
                'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
            ],
            requestedBy: $hrApproverEmployeeId,
            actor: new AuditActor(actorId: $hrApproverEmployeeId, actorRole: 'hr_approver'),
        );

        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/pegawai-baru/{$requestId}/setujui");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('pending', DB::table('emp_new_employee_requests')->where('id', $requestId)->value('status'));
        $this->assertSame(0, DB::table('emp_employees')->where('nrp', '2099.01.0003')->count());
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
