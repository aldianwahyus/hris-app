<?php

declare(strict_types=1);

namespace Tests\Feature\Lms;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Assessment Center (BRD §5.4) — bank soal MILIK satu assessment,
 * scoring otomatis (pilihan ganda) langsung saat submit, esai menunggu
 * penilaian manual satu assessor (simplifikasi "multi-assessor", lihat
 * docblock GradeAssessmentAttempt).
 */
final class LmsAssessmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hc_dapat_membuat_asesmen_dan_soal(): void
    {
        $response = $this->actingAs($this->hrAdmin())->post('/admin/pelatihan/asesmen', [
            'title' => 'Asesmen Uji',
            'passing_score' => 70,
        ]);
        $response->assertRedirect(route('lms.admin.assessments.index'));

        $assessmentId = DB::table('lms_assessments')->where('title', 'Asesmen Uji')->value('id');

        $soal = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/asesmen/{$assessmentId}/soal", [
            'sequence' => 1,
            'question_text' => 'Ibu kota NTB?',
            'type' => 'multiple_choice',
            'options' => ['A' => 'Mataram', 'B' => 'Denpasar'],
            'correct_option' => 'A',
            'score_weight' => 1,
        ]);

        $soal->assertRedirect(route('lms.admin.assessments.questions', $assessmentId));
        $this->assertSame(1, DB::table('lms_assessment_questions')->where('assessment_id', $assessmentId)->count());
    }

    public function test_asesmen_pilihan_ganda_saja_langsung_ternilai_otomatis(): void
    {
        $assessmentId = $this->seedAssessment();
        $this->seedMcQuestion($assessmentId, 1, 'A', 1.0);
        $this->seedMcQuestion($assessmentId, 2, 'B', 1.0);

        $siti = $this->userWithNrp('2018.03.0142');

        $start = $this->actingAs($siti)->post("/pelatihan/asesmen/{$assessmentId}/mulai");
        $attemptId = DB::table('lms_assessment_attempts')->where('assessment_id', $assessmentId)->where('employee_id', $siti->employee_id)->value('id');
        $start->assertRedirect(route('lms.assessment.take', $attemptId));

        $questionIds = DB::table('lms_assessment_questions')->where('assessment_id', $assessmentId)->orderBy('sequence')->pluck('id');

        $submit = $this->actingAs($siti)->post("/pelatihan/asesmen/kerjakan/{$attemptId}", [
            'jawaban' => [$questionIds[0] => 'A', $questionIds[1] => 'A'], // baris 2 salah (jawaban benar B)
        ]);

        $submit->assertRedirect(route('lms.assessment.result', $attemptId));

        $attempt = DB::table('lms_assessment_attempts')->where('id', $attemptId)->first();
        $this->assertSame('scored', $attempt->status);
        $this->assertEquals(1.0, $attempt->total_score); // 1 benar (1.0) + 1 salah (0)
        $this->assertFalse((bool) $attempt->passed); // passing_score 70, skor jauh di bawah
    }

    public function test_asesmen_dengan_esai_menunggu_penilaian_manual(): void
    {
        $assessmentId = $this->seedAssessment();
        $this->seedMcQuestion($assessmentId, 1, 'A', 1.0);
        $essayQuestionId = $this->seedEssayQuestion($assessmentId, 2, 5.0);

        $siti = $this->userWithNrp('2018.03.0142');
        $this->actingAs($siti)->post("/pelatihan/asesmen/{$assessmentId}/mulai");
        $attemptId = DB::table('lms_assessment_attempts')->where('assessment_id', $assessmentId)->where('employee_id', $siti->employee_id)->value('id');

        $mcQuestionId = DB::table('lms_assessment_questions')->where('assessment_id', $assessmentId)->where('sequence', 1)->value('id');

        $this->actingAs($siti)->post("/pelatihan/asesmen/kerjakan/{$attemptId}", [
            'jawaban' => [$mcQuestionId => 'A', $essayQuestionId => 'Jawaban esai saya.'],
        ]);

        $this->assertSame('submitted', DB::table('lms_assessment_attempts')->where('id', $attemptId)->value('status'));

        $hrAdmin = $this->hrAdmin();
        $grade = $this->actingAs($hrAdmin)->post("/admin/pelatihan/asesmen/percobaan/{$attemptId}/nilai", [
            'skor' => [$essayQuestionId => 4],
        ]);

        $grade->assertRedirect(route('lms.admin.assessments.attempts', $assessmentId));

        $attempt = DB::table('lms_assessment_attempts')->where('id', $attemptId)->first();
        $this->assertSame('scored', $attempt->status);
        $this->assertEquals(5.0, $attempt->total_score); // 1.0 (MC) + 4.0 (esai)
    }

    /**
     * Regresi (bug ditemukan lewat audit kode): handle() sebelumnya tidak
     * pernah membandingkan assessorId dengan attempt->employee_id — siapa
     * pun yang punya izin lms-catalog.manage (mis. hr_admin) bisa
     * mengerjakan asesmen sebagai pegawai ESS lalu meluluskan dirinya
     * sendiri tanpa peninjauan independen sama sekali.
     */
    public function test_penilai_tidak_dapat_menilai_pengerjaannya_sendiri(): void
    {
        $assessmentId = $this->seedAssessment();
        $essayQuestionId = $this->seedEssayQuestion($assessmentId, 1, 5.0);

        $hrAdmin = $this->hrAdmin();
        $this->actingAs($hrAdmin)->post("/pelatihan/asesmen/{$assessmentId}/mulai");
        $attemptId = DB::table('lms_assessment_attempts')->where('assessment_id', $assessmentId)->where('employee_id', $hrAdmin->employee_id)->value('id');

        $this->actingAs($hrAdmin)->post("/pelatihan/asesmen/kerjakan/{$attemptId}", [
            'jawaban' => [$essayQuestionId => 'Jawaban esai saya sendiri.'],
        ]);

        $this->assertSame('submitted', DB::table('lms_assessment_attempts')->where('id', $attemptId)->value('status'));

        $response = $this->actingAs($hrAdmin)->post("/admin/pelatihan/asesmen/percobaan/{$attemptId}/nilai", [
            'skor' => [$essayQuestionId => 5],
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame('submitted', DB::table('lms_assessment_attempts')->where('id', $attemptId)->value('status'), 'Percobaan tidak boleh berubah jadi scored oleh penilaian diri sendiri.');
    }

    /**
     * Regresi (bug ditemukan lewat audit kode): validasi HANYA memeriksa
     * min:0, tidak pernah memeriksa terhadap score_weight soal — atribut
     * "max" pada input HTML cuma kosmetik sisi klien, bisa dilewati lewat
     * POST langsung, sehingga skor total (dan status lulus) bisa
     * digelembungkan melebihi bobot soal yang sebenarnya.
     */
    public function test_skor_esai_tidak_boleh_melebihi_bobot_soal(): void
    {
        $assessmentId = $this->seedAssessment();
        $essayQuestionId = $this->seedEssayQuestion($assessmentId, 1, 5.0);

        $siti = $this->userWithNrp('2018.03.0142');
        $this->actingAs($siti)->post("/pelatihan/asesmen/{$assessmentId}/mulai");
        $attemptId = DB::table('lms_assessment_attempts')->where('assessment_id', $assessmentId)->where('employee_id', $siti->employee_id)->value('id');

        $this->actingAs($siti)->post("/pelatihan/asesmen/kerjakan/{$attemptId}", [
            'jawaban' => [$essayQuestionId => 'Jawaban esai saya.'],
        ]);

        $response = $this->actingAs($this->hrAdmin())->post("/admin/pelatihan/asesmen/percobaan/{$attemptId}/nilai", [
            'skor' => [$essayQuestionId => 100], // bobot soal cuma 5.0
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame('submitted', DB::table('lms_assessment_attempts')->where('id', $attemptId)->value('status'), 'Percobaan tidak boleh ternilai dengan skor melebihi bobot soal.');
    }

    public function test_peran_lain_ditolak_dari_admin_asesmen(): void
    {
        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->get('/admin/pelatihan/asesmen');

        $response->assertForbidden();
    }

    public function test_pegawai_biasa_dapat_mengakses_daftar_asesmen(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/pelatihan/asesmen');

        $response->assertOk();
    }

    private function seedAssessment(): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_assessments')->insert([
            'id' => $id, 'title' => 'Asesmen Uji '.uniqid(), 'passing_score' => 70,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedMcQuestion(string $assessmentId, int $sequence, string $correctOption, float $weight): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_assessment_questions')->insert([
            'id' => $id, 'assessment_id' => $assessmentId, 'sequence' => $sequence,
            'question_text' => 'Soal '.$sequence, 'type' => 'multiple_choice',
            'options' => json_encode(['A' => 'Opsi A', 'B' => 'Opsi B']),
            'correct_option' => $correctOption, 'score_weight' => $weight,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function seedEssayQuestion(string $assessmentId, int $sequence, float $weight): string
    {
        $id = (string) Uuid7::generate();
        DB::table('lms_assessment_questions')->insert([
            'id' => $id, 'assessment_id' => $assessmentId, 'sequence' => $sequence,
            'question_text' => 'Soal esai '.$sequence, 'type' => 'essay',
            'score_weight' => $weight,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        return $id;
    }

    private function hrAdmin(): User
    {
        return $this->userWithNrp('2021.05.0302');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
