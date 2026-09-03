<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rekrutmen (ATS) — modul baru (evaluasi PM/client 2026-09-02),
 * TERBESAR dari 9 modul. Requisition maker-checker (permission
 * TERPISAH recruitment-requisition.decide, hr_approver saja) →
 * lowongan PUBLIK tanpa login → pipeline → tawaran (token publik) →
 * bridging ke SubmitNewEmployeeRequest yang SUDAH ADA.
 */
final class RecruitmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_mengajukan_requisition_dan_hr_approver_menyetujui(): void
    {
        $requisitionId = $this->submitRequisition();

        $approve = $this->actingAs($this->userWithNrp('2014.02.0061'))
            ->post("/persetujuan/rekrutmen/requisition/{$requisitionId}/setujui");

        $approve->assertRedirect(route('admin.recruitment-requisition-show', $requisitionId));
        $this->assertSame('approved', DB::table('rec_job_requisitions')->where('id', $requisitionId)->value('status'));
    }

    public function test_hr_admin_tidak_bisa_mengajukan_requisition_kantor_lain(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302'); // Rina, KCP Gerung
        $otherOffice = DB::table('md_offices')->where('id', '!=', $this->officeId('2021.05.0302'))->value('id');

        $response = $this->actingAs($hrAdmin)->post('/persetujuan/rekrutmen/requisition/buat', [
            'office_id' => $otherOffice,
            'position_id' => $this->positionId(),
            'requested_headcount' => 1,
            'justification' => 'Uji lingkup kantor.',
        ]);

        $response->assertForbidden();
    }

    public function test_hr_approver_tidak_bisa_menyetujui_requisition_miliknya_sendiri(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrApprover)->post('/persetujuan/rekrutmen/requisition/buat', [
            'office_id' => $this->officeId('2014.02.0061'),
            'position_id' => $this->positionId(),
            'requested_headcount' => 1,
            'justification' => 'Uji tolak swa-setuju.',
        ]);
        $requisitionId = DB::table('rec_job_requisitions')->where('requested_by', $this->employeeId('2014.02.0061'))->value('id');

        $response = $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/requisition/{$requisitionId}/setujui");

        $response->assertRedirect(route('admin.recruitment-requisition-show', $requisitionId));
        $response->assertSessionHas('gagal');
        $this->assertSame('pending', DB::table('rec_job_requisitions')->where('id', $requisitionId)->value('status'));
    }

    public function test_lowongan_tidak_bisa_dibuka_dari_requisition_yang_belum_disetujui(): void
    {
        $requisitionId = $this->submitRequisition();
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post('/persetujuan/rekrutmen/lowongan/buat', [
            'requisition_id' => $requisitionId,
            'title' => 'Teller Uji',
            'description' => 'Deskripsi uji.',
            'requirements' => 'Persyaratan uji.',
            'employment_status_offered' => 'tetap',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('rec_job_postings')->where('requisition_id', $requisitionId)->count());
    }

    public function test_tidak_bisa_melamar_lowongan_yang_sudah_ditutup(): void
    {
        $postingId = $this->openPosting();
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lowongan/{$postingId}/tutup");

        $response = $this->post("/lowongan/{$postingId}/lamar", [
            'full_name' => 'Kandidat Uji',
            'email' => 'kandidat.uji.'.uniqid().'@contoh.test',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('rec_applications')->where('posting_id', $postingId)->count());
    }

    public function test_tidak_bisa_melamar_posisi_yang_sama_dua_kali(): void
    {
        $postingId = $this->openPosting();
        $email = 'kandidat.dobel.'.uniqid().'@contoh.test';

        $this->post("/lowongan/{$postingId}/lamar", ['full_name' => 'Kandidat Dobel', 'email' => $email]);
        $response = $this->post("/lowongan/{$postingId}/lamar", ['full_name' => 'Kandidat Dobel', 'email' => $email]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('rec_applications')->where('posting_id', $postingId)->count());
    }

    public function test_alur_lengkap_dari_lamaran_sampai_diusulkan_jadi_pegawai(): void
    {
        $postingId = $this->openPosting();
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $email = 'kandidat.lengkap.'.uniqid().'@contoh.test';

        // Publik: melamar TANPA login.
        $apply = $this->post("/lowongan/{$postingId}/lamar", [
            'full_name' => 'Kandidat Lengkap',
            'email' => $email,
            'phone' => '08123456789',
        ]);
        $apply->assertOk();
        $applicationId = DB::table('rec_applications')
            ->join('rec_candidates', 'rec_candidates.id', '=', 'rec_applications.candidate_id')
            ->where('rec_candidates.email', $email)
            ->value('rec_applications.id');
        $this->assertNotNull($applicationId);
        $this->assertSame('melamar', DB::table('rec_applications')->where('id', $applicationId)->value('status'));

        // HC memajukan tahap.
        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/tahap", ['status' => 'seleksi_berkas']);
        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/tahap", ['status' => 'wawancara']);

        // Jadwalkan + catat hasil wawancara.
        $schedule = $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/wawancara", [
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'location_or_link' => 'Kantor Pusat, Lt. 3',
        ]);
        $schedule->assertRedirect(route('admin.recruitment-application-show', $applicationId));
        $interviewId = DB::table('rec_interview_schedules')->where('application_id', $applicationId)->value('id');

        $feedback = $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/wawancara/{$interviewId}/feedback", [
            'feedback' => 'Kandidat menjawab dengan baik.',
            'rating' => 5,
        ]);
        $feedback->assertRedirect(route('admin.recruitment-application-show', $applicationId));
        $this->assertSame('selesai', DB::table('rec_interview_schedules')->where('id', $interviewId)->value('status'));

        // Lanjut ke tahap penawaran + buat tawaran.
        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/tahap", ['status' => 'penawaran']);

        $offer = $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/tawaran", [
            'proposed_position_id' => $this->positionId(),
            'proposed_office_id' => $this->officeId('2014.02.0061'),
            'proposed_salary_notes' => 'Sesuai golongan.',
        ]);
        $offer->assertRedirect(route('admin.recruitment-application-show', $applicationId));
        $token = DB::table('rec_job_offers')->where('application_id', $applicationId)->value('response_token');
        $this->assertNotNull($token);

        // Publik: kandidat menerima tawaran lewat tautan token, TANPA login.
        $offerPage = $this->get("/tawaran/{$token}");
        $offerPage->assertOk();

        $accept = $this->post("/tawaran/{$token}", ['keputusan' => 'terima']);
        $accept->assertRedirect(route('careers.offer', $token));
        $this->assertSame('diterima', DB::table('rec_job_offers')->where('response_token', $token)->value('status'));
        $this->assertSame('diterima', DB::table('rec_applications')->where('id', $applicationId)->value('status'));

        // HC memproses kandidat jadi usulan pegawai baru.
        $nrp = '2099.09.'.substr(uniqid(), -4);
        $convert = $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/jadikan-pegawai", [
            'nrp' => $nrp,
            'join_date' => now()->addDays(14)->format('Y-m-d'),
        ]);
        $convert->assertRedirect(route('admin.recruitment-application-show', $applicationId));
        $convert->assertSessionHas('sukses');

        $newEmployeeRequest = DB::table('emp_new_employee_requests')->where('status', 'pending')
            ->get()->first(fn ($r) => (json_decode($r->proposed_data, true)['nrp'] ?? null) === $nrp);
        $this->assertNotNull($newEmployeeRequest);
    }

    public function test_kandidat_menolak_tawaran_menutup_lamaran(): void
    {
        $postingId = $this->openPosting();
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $email = 'kandidat.tolak.'.uniqid().'@contoh.test';

        $this->post("/lowongan/{$postingId}/lamar", ['full_name' => 'Kandidat Tolak', 'email' => $email]);
        $applicationId = DB::table('rec_applications')
            ->join('rec_candidates', 'rec_candidates.id', '=', 'rec_applications.candidate_id')
            ->where('rec_candidates.email', $email)
            ->value('rec_applications.id');

        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/tahap", ['status' => 'penawaran']);
        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/tawaran", [
            'proposed_position_id' => $this->positionId(),
            'proposed_office_id' => $this->officeId('2014.02.0061'),
        ]);
        $token = DB::table('rec_job_offers')->where('application_id', $applicationId)->value('response_token');

        $this->post("/tawaran/{$token}", ['keputusan' => 'tolak']);

        $this->assertSame('ditolak', DB::table('rec_job_offers')->where('response_token', $token)->value('status'));
        $this->assertSame('ditolak', DB::table('rec_applications')->where('id', $applicationId)->value('status'));
    }

    private function submitRequisition(): string
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302');

        $this->actingAs($hrAdmin)->post('/persetujuan/rekrutmen/requisition/buat', [
            'office_id' => $this->officeId('2021.05.0302'),
            'position_id' => $this->positionId(),
            'requested_headcount' => 1,
            'justification' => 'Kebutuhan uji otomatis.',
        ]);

        return DB::table('rec_job_requisitions')->where('requested_by', $this->employeeId('2021.05.0302'))->latest('created_at')->value('id');
    }

    private function openPosting(): string
    {
        $requisitionId = $this->submitRequisition();
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/requisition/{$requisitionId}/setujui");

        $this->actingAs($hrApprover)->post('/persetujuan/rekrutmen/lowongan/buat', [
            'requisition_id' => $requisitionId,
            'title' => 'Teller Uji '.uniqid(),
            'description' => 'Deskripsi uji.',
            'requirements' => 'Persyaratan uji.',
            'employment_status_offered' => 'tetap',
        ]);

        return DB::table('rec_job_postings')->where('requisition_id', $requisitionId)->value('id');
    }

    private function positionId(): string
    {
        return DB::table('md_positions')->where('code', 'OFC')->value('id');
    }

    private function officeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('office_id');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        return User::query()->where('employee_id', $this->employeeId($nrp))->firstOrFail();
    }
}
