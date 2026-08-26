<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SEC-2026-08-ERR: SppdTariffNotFound (dilempar EloquentSppdTariffRepository
 * saat tidak ada baris tarif yang cocok) dulu tidak tertangkap di
 * SppdRequestController::store() — bubble ke halaman 500 mentah alih-alih
 * pesan "gagal" yang bisa dipahami pemohon.
 */
final class SppdRequestControllerErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pengajuan_sppd_tanpa_tarif_yang_cocok_menampilkan_pesan_gagal_bukan_500(): void
    {
        // Hapus SELURUH tarif Uang Makan Jarak Pendek — memaksa
        // SppdTariffNotFound saat SppdBudgetCalculator mencari tarif.
        DB::table('pay_sppd_tariffs')
            ->where('component', 'uang_makan')
            ->where('trip_category', 'jarak_pendek')
            ->delete();

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post('/sppd/ajukan', [
                'trip_category' => 'jarak_pendek',
                'destination' => 'Kota Bima',
                'purpose' => 'Rapat koordinasi cabang.',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-01',
                'radius_band' => '30_100',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame(
            0,
            DB::table('spd_requests')
                ->where('employee_id', $this->employeeId('2018.03.0142'))
                ->where('destination', 'Kota Bima')
                ->count(),
            'Tidak boleh ada pengajuan SPPD yang tersimpan saat tarif tidak ditemukan.'
        );
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
