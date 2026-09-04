<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Core\Domain\Uuid7;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** ESS Mobile (Fase 2) — Survei Keterlibatan, cermin SurveyController (SubmitSurveyResponse). */
final class SurveyApiTest extends TestCase
{
    use DatabaseTransactions;

    private const NRP = '2018.03.0142'; // Siti Rahmawati

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
    }

    public function test_daftar_survei_menampilkan_survei_bank_wide_aktif(): void
    {
        $surveyId = $this->seedActiveSurvey();

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/survei');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $surveyId]);
    }

    public function test_dapat_melihat_pertanyaan_dan_mengisi_survei(): void
    {
        $surveyId = $this->seedActiveSurvey();
        $questionId = DB::table('svy_questions')->where('survey_id', $surveyId)->value('id');
        $token = $this->token();

        $show = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/survei/{$surveyId}");
        $show->assertOk();
        $show->assertJsonFragment(['id' => $questionId]);

        $submit = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/survei/{$surveyId}/isi", [
            'jawaban' => [$questionId => '9'],
        ]);

        $submit->assertOk();
        $this->assertTrue(
            DB::table('svy_response_tokens')
                ->where('survey_id', $surveyId)
                ->where('employee_id', $this->employeeId(self::NRP))
                ->exists()
        );
    }

    public function test_tidak_bisa_mengisi_survei_yang_sama_dua_kali(): void
    {
        $surveyId = $this->seedActiveSurvey();
        $questionId = DB::table('svy_questions')->where('survey_id', $surveyId)->value('id');
        $token = $this->token();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/survei/{$surveyId}/isi", [
            'jawaban' => [$questionId => '9'],
        ]);

        $second = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/survei/{$surveyId}");

        $second->assertForbidden();
    }

    private function seedActiveSurvey(): string
    {
        $hrApproverId = $this->employeeId('2014.02.0061');
        $surveyId = (string) Uuid7::generate();

        DB::table('svy_surveys')->insert([
            'id' => $surveyId, 'title' => 'Survei Uji API', 'type' => 'enps', 'scope' => 'bank_wide',
            'is_anonymous' => false, 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'status' => 'aktif', 'created_by' => $hrApproverId, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        DB::table('svy_questions')->insert([
            'id' => (string) Uuid7::generate(), 'survey_id' => $surveyId,
            'question_text' => 'Seberapa besar kemungkinan Anda merekomendasikan Bank NTB Syariah?',
            'question_type' => 'nps_0_10', 'display_order' => 1,
        ]);

        return $surveyId;
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'nrp' => self::NRP,
            'password' => self::PASSWORD,
        ])->json('token');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }
}
