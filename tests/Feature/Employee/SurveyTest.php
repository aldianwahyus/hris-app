<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Survey\Application\ComputeSurveyResults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Survei Keterlibatan (eNPS/Pulse) — modul baru (evaluasi PM/client
 * 2026-09-02). hr_admin kantornya sendiri + bank-wide, hr_approver
 * seluruhnya (pola PERSIS antrean lain). Token pencegah pengisian
 * ganda SELALU per employee_id, terlepas dari anonimitas survei.
 */
final class SurveyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_approver_dapat_membuat_dan_menerbitkan_survei_enps(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $store = $this->actingAs($hrApprover)->post('/persetujuan/survei/buat', [
            'title' => 'Survei eNPS Q3',
            'type' => 'enps',
            'scope' => 'bank_wide',
            'start_date' => now()->subDay()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'questions' => [
                ['question_text' => 'Seberapa besar kemungkinan Anda merekomendasikan Bank NTB Syariah sebagai tempat kerja?', 'question_type' => 'nps_0_10'],
            ],
        ]);

        $surveyId = DB::table('svy_surveys')->where('title', 'Survei eNPS Q3')->value('id');
        $this->assertNotNull($surveyId);
        $this->assertSame('draft', DB::table('svy_surveys')->where('id', $surveyId)->value('status'));
        $store->assertRedirect(route('admin.survey-show', $surveyId));

        $publish = $this->actingAs($hrApprover)->post("/persetujuan/survei/{$surveyId}/terbitkan");
        $publish->assertRedirect(route('admin.survey-show', $surveyId));
        $this->assertSame('aktif', DB::table('svy_surveys')->where('id', $surveyId)->value('status'));
    }

    public function test_survei_tanpa_pertanyaan_ditolak(): void
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $response = $this->actingAs($hrApprover)->post('/persetujuan/survei/buat', [
            'title' => 'Survei Kosong',
            'type' => 'kustom',
            'scope' => 'bank_wide',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'questions' => [],
        ]);

        $response->assertSessionHasErrors('questions');
        $this->assertSame(0, DB::table('svy_surveys')->where('title', 'Survei Kosong')->count());
    }

    public function test_pegawai_dapat_mengisi_survei_yang_aktif_dan_menyasar_kantornya(): void
    {
        $surveyId = $this->createActiveSurvey('rating_1_5', scope: 'bank_wide');
        $questionId = DB::table('svy_questions')->where('survey_id', $surveyId)->value('id');
        $pegawai = $this->userWithNrp('2018.03.0142');

        $indexBefore = $this->actingAs($pegawai)->get('/survei');
        $indexBefore->assertOk();
        $indexBefore->assertSeeText('Isi Survei');

        $response = $this->actingAs($pegawai)->post("/survei/{$surveyId}", [
            'jawaban' => [$questionId => '4'],
        ]);

        $response->assertRedirect(route('survey.index'));
        $this->assertSame(1, DB::table('svy_responses')->where('survey_id', $surveyId)->count());
        $this->assertSame(1, DB::table('svy_response_tokens')->where('survey_id', $surveyId)->where('employee_id', $this->employeeId('2018.03.0142'))->count());

        $indexAfter = $this->actingAs($pegawai)->get('/survei');
        $indexAfter->assertSeeText('Sudah Diisi');
    }

    public function test_pegawai_tidak_bisa_mengisi_survei_yang_sama_dua_kali(): void
    {
        $surveyId = $this->createActiveSurvey('rating_1_5', scope: 'bank_wide');
        $questionId = DB::table('svy_questions')->where('survey_id', $surveyId)->value('id');
        $pegawai = $this->userWithNrp('2018.03.0142');

        $this->actingAs($pegawai)->post("/survei/{$surveyId}", ['jawaban' => [$questionId => '4']]);
        $response = $this->actingAs($pegawai)->post("/survei/{$surveyId}", ['jawaban' => [$questionId => '5']]);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('svy_responses')->where('survey_id', $surveyId)->count());
    }

    public function test_survei_lingkup_kantor_tidak_terlihat_oleh_pegawai_kantor_lain(): void
    {
        $gerungOffice = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('office_id');
        $surveyId = $this->createActiveSurvey('teks', scope: 'office', officeId: $gerungOffice);

        $pegawaiLain = $this->userWithNrp('2018.03.0142'); // kantor lain
        $response = $this->actingAs($pegawaiLain)->get("/survei/{$surveyId}");

        $response->assertNotFound();
    }

    public function test_survei_anonim_tidak_menyimpan_employee_id_pada_jawaban(): void
    {
        $surveyId = $this->createActiveSurvey('teks', scope: 'bank_wide', anonymous: true);
        $questionId = DB::table('svy_questions')->where('survey_id', $surveyId)->value('id');
        $pegawai = $this->userWithNrp('2018.03.0142');

        $this->actingAs($pegawai)->post("/survei/{$surveyId}", ['jawaban' => [$questionId => 'Cukup baik.']]);

        $response = DB::table('svy_responses')->where('survey_id', $surveyId)->first();
        $this->assertNotNull($response);
        $this->assertNull($response->employee_id);
        // Token pencegah duplikat TETAP tercatat meski jawabannya anonim.
        $this->assertSame(1, DB::table('svy_response_tokens')->where('survey_id', $surveyId)->where('employee_id', $this->employeeId('2018.03.0142'))->count());
    }

    public function test_hasil_enps_menghitung_skor_dengan_benar(): void
    {
        $surveyId = $this->createActiveSurvey('nps_0_10', scope: 'bank_wide');
        $questionId = DB::table('svy_questions')->where('survey_id', $surveyId)->value('id');

        // 1 promoter (10), 1 pasif (7), 1 detractor (3) → skor = 33.3 - 33.3 = 0.0
        $this->actingAs($this->userWithNrp('2018.03.0142'))->post("/survei/{$surveyId}", ['jawaban' => [$questionId => '10']]);
        $this->actingAs($this->userWithNrp('2017.11.0119'))->post("/survei/{$surveyId}", ['jawaban' => [$questionId => '7']]);
        $this->actingAs($this->userWithNrp('2021.05.0302'))->post("/survei/{$surveyId}", ['jawaban' => [$questionId => '3']]);

        $this->assertSame(3, DB::table('svy_responses')->where('survey_id', $surveyId)->count());

        $results = app(ComputeSurveyResults::class)->handle($surveyId);
        $this->assertSame(3, $results['response_count']);
        $this->assertSame(0.0, $results['questions'][0]['summary']['score']);
        $this->assertSame(1, $results['questions'][0]['summary']['promoter']);
        $this->assertSame(1, $results['questions'][0]['summary']['passive']);
        $this->assertSame(1, $results['questions'][0]['summary']['detractor']);

        $show = $this->actingAs($this->userWithNrp('2014.02.0061'))->get("/persetujuan/survei/{$surveyId}");
        $show->assertOk();
        $show->assertSeeText('Skor eNPS');
    }

    public function test_hr_admin_hanya_melihat_survei_bank_wide_dan_kantornya_sendiri(): void
    {
        $gerungOffice = DB::table('emp_employees')->where('nrp', '2021.05.0302')->value('office_id');
        $this->createActiveSurvey('teks', scope: 'office', officeId: $gerungOffice, title: 'Survei Gerung');

        $otherOffice = DB::table('emp_employees')->where('nrp', '2018.03.0142')->value('office_id');
        $this->createActiveSurvey('teks', scope: 'office', officeId: $otherOffice, title: 'Survei Kantor Lain');

        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->get('/persetujuan/survei');

        $response->assertOk();
        $response->assertSeeText('Survei Gerung');
        $response->assertDontSeeText('Survei Kantor Lain');
    }

    private function createActiveSurvey(string $questionType, string $scope = 'bank_wide', ?string $officeId = null, bool $anonymous = false, string $title = 'Survei Uji'): string
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');
        $uniqueTitle = $title === 'Survei Uji' ? $title.' '.uniqid() : $title;

        $payload = [
            'title' => $uniqueTitle,
            'type' => 'kustom',
            'scope' => $scope,
            'is_anonymous' => $anonymous ? '1' : '0',
            'start_date' => now()->subDay()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'questions' => [
                ['question_text' => 'Pertanyaan uji', 'question_type' => $questionType],
            ],
        ];

        if ($officeId !== null) {
            $payload['office_id'] = $officeId;
        }

        $this->actingAs($hrApprover)->post('/persetujuan/survei/buat', $payload);
        $surveyId = DB::table('svy_surveys')->where('title', $uniqueTitle)->value('id');
        $this->actingAs($hrApprover)->post("/persetujuan/survei/{$surveyId}/terbitkan");

        return $surveyId;
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
