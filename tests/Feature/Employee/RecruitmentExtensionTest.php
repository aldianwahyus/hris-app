<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perluasan Rekrutmen (Fase 2) — portal status kandidat (status_token,
 * PUBLIK) + unduh kalender wawancara (.ics, PHP murni TANPA API
 * eksternal). Perluasan KECIL modul Rekrutmen Fase 1, BUKAN modul baru.
 */
final class RecruitmentExtensionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kandidat_dapat_memeriksa_status_lamaran_lewat_token(): void
    {
        $postingId = $this->openPosting();
        $email = 'kandidat.status.'.uniqid().'@contoh.test';

        $this->post("/lowongan/{$postingId}/lamar", ['full_name' => 'Kandidat Status', 'email' => $email]);

        $token = DB::table('rec_applications')
            ->join('rec_candidates', 'rec_candidates.id', '=', 'rec_applications.candidate_id')
            ->where('rec_candidates.email', $email)
            ->value('rec_applications.status_token');
        $this->assertNotNull($token);

        $response = $this->get("/lowongan/status/{$token}");

        $response->assertOk();
        $response->assertSeeText('Kandidat Status');
        $response->assertSeeText('Melamar');
    }

    public function test_token_status_tidak_valid_menghasilkan_404(): void
    {
        $response = $this->get('/lowongan/status/token-tidak-ada');

        $response->assertNotFound();
    }

    public function test_hc_dapat_mengunduh_kalender_ics_wawancara(): void
    {
        $postingId = $this->openPosting();
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $email = 'kandidat.ics.'.uniqid().'@contoh.test';

        $this->post("/lowongan/{$postingId}/lamar", ['full_name' => 'Kandidat ICS', 'email' => $email]);
        $applicationId = DB::table('rec_applications')
            ->join('rec_candidates', 'rec_candidates.id', '=', 'rec_applications.candidate_id')
            ->where('rec_candidates.email', $email)
            ->value('rec_applications.id');

        $this->actingAs($hrApprover)->post("/persetujuan/rekrutmen/lamaran/{$applicationId}/wawancara", [
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'location_or_link' => 'Kantor Pusat, Lt. 3',
        ]);
        $interviewId = DB::table('rec_interview_schedules')->where('application_id', $applicationId)->value('id');

        $response = $this->actingAs($hrApprover)->get("/persetujuan/rekrutmen/lamaran/{$applicationId}/wawancara/{$interviewId}/ics");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=UTF-8');
        $response->assertSeeText('BEGIN:VCALENDAR', false);
        $response->assertSeeText('Kandidat ICS', false);
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
