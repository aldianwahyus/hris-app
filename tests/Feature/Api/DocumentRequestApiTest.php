<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** ESS Mobile (Fase 2) — Layanan Dokumen Mandiri, cermin DocumentRequestController. */
final class DocumentRequestApiTest extends TestCase
{
    use DatabaseTransactions;

    private const NRP = '2018.03.0142'; // Siti Rahmawati

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
    }

    public function test_dokumen_dapat_diajukan_lewat_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/dokumen', [
            'document_type' => 'surat_keterangan_kerja',
            'purpose' => 'Persyaratan pengajuan KPR (uji API).',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id']);
        $this->assertSame('pending', DB::table('doc_requests')->where('id', $response->json('id'))->value('status'));
    }

    public function test_jenis_dokumen_tidak_valid_ditolak(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->postJson('/api/v1/dokumen', [
            'document_type' => 'jenis_tidak_ada',
            'purpose' => 'Uji.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('document_type');
    }

    public function test_daftar_dokumen_terbatas_pada_milik_sendiri(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/dokumen', [
            'document_type' => 'surat_referensi',
            'purpose' => 'Uji daftar.',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/dokumen');

        $response->assertOk();
        $response->assertJsonFragment(['purpose' => 'Uji daftar.']);
    }

    public function test_tidak_bisa_mengunduh_dokumen_yang_belum_diterbitkan(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/dokumen', [
            'document_type' => 'surat_referensi',
            'purpose' => 'Uji unduh.',
        ]);
        $id = DB::table('doc_requests')->where('employee_id', $this->employeeId(self::NRP))->latest('created_at')->value('id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/dokumen/{$id}/unduh");

        $response->assertNotFound();
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
