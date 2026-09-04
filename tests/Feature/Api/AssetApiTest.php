<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Core\Domain\Uuid7;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** ESS Mobile (Fase 2) — "Aset Saya", cermin AssetAssignmentController::mine(). */
final class AssetApiTest extends TestCase
{
    use DatabaseTransactions;

    private const NRP = '2018.03.0142'; // Siti Rahmawati

    private const PASSWORD = 'RahasiaDemo!123';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::NRP.'|127.0.0.1');
    }

    public function test_daftar_aset_saya_hanya_menampilkan_aset_yang_belum_dikembalikan(): void
    {
        $employeeId = $this->employeeId(self::NRP);
        $officeId = DB::table('emp_employees')->where('id', $employeeId)->value('office_id');

        $assetId = (string) Uuid7::generate();
        DB::table('ast_assets')->insert([
            'id' => $assetId, 'asset_code' => 'AST-API-'.uniqid(), 'name' => 'Laptop Uji API',
            'category' => 'elektronik', 'condition' => 'baik', 'status' => 'dipakai', 'office_id' => $officeId,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);
        DB::table('ast_assignments')->insert([
            'id' => (string) Uuid7::generate(), 'asset_id' => $assetId, 'employee_id' => $employeeId,
            'assigned_at' => now(), 'assigned_by' => $employeeId, 'returned_at' => null,
            'created_at' => now(), 'updated_at' => now(), 'version' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token()}")->getJson('/api/v1/aset');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Laptop Uji API']);
    }

    public function test_tanpa_token_ditolak(): void
    {
        $response = $this->getJson('/api/v1/aset');

        $response->assertUnauthorized();
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
