<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Core\Domain\Uuid7;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tanda Tangan Elektronik (internal) — modul baru (evaluasi PM/client
 * 2026-09-02). Diterapkan pertama ke SK (Surat Keputusan) — pola
 * generik lintas signable_type, lihat SignatureController/SignDocument.
 */
final class ElectronicSignatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_menandatangani_sk_dengan_gambar(): void
    {
        [$letterId, $skNumber] = $this->createDecisionLetter();
        $hrAdmin = $this->userWithNrp('2021.05.0302');

        $response = $this->actingAs($hrAdmin)->post("/tanda-tangan/decision_letter/{$letterId}", [
            'signature_image_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]);

        $response->assertRedirect(route('sk.index'));
        $response->assertSessionHas('sukses');

        $signature = DB::table('sig_signatures')->where('signable_type', 'decision_letter')->where('signable_id', $letterId)->first();
        $this->assertNotNull($signature);
        $this->assertNotNull($signature->signature_image_base64);
        $this->assertNull($signature->typed_name);
        $this->assertSame(64, strlen($signature->document_hash));

        $audit = DB::table('aud_change_logs')
            ->where('auditable_type', 'decision_letter')->where('auditable_id', $letterId)
            ->where('action', 'signed')->first();
        $this->assertNotNull($audit);
        $this->assertSame($skNumber, $audit->context_ref);
    }

    public function test_dapat_menandatangani_dengan_nama_ketik_saja(): void
    {
        [$letterId] = $this->createDecisionLetter();
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysAdmin)->post("/tanda-tangan/decision_letter/{$letterId}", [
            'typed_name' => 'Administrator Sistem',
        ]);

        $response->assertRedirect();
        $signature = DB::table('sig_signatures')->where('signable_id', $letterId)->first();
        $this->assertNotNull($signature);
        $this->assertSame('Administrator Sistem', $signature->typed_name);
        $this->assertNull($signature->signature_image_base64);
    }

    public function test_tanda_tangan_kosong_ditolak(): void
    {
        [$letterId] = $this->createDecisionLetter();
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysAdmin)->post("/tanda-tangan/decision_letter/{$letterId}", []);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('sig_signatures')->where('signable_id', $letterId)->count());
    }

    public function test_tidak_bisa_menandatangani_dua_kali(): void
    {
        [$letterId] = $this->createDecisionLetter();
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $this->actingAs($sysAdmin)->post("/tanda-tangan/decision_letter/{$letterId}", ['typed_name' => 'Administrator Sistem']);
        $response = $this->actingAs($sysAdmin)->post("/tanda-tangan/decision_letter/{$letterId}", ['typed_name' => 'Administrator Sistem']);

        $response->assertSessionHas('gagal');
        $this->assertSame(1, DB::table('sig_signatures')->where('signable_id', $letterId)->count());
    }

    public function test_dua_pejabat_berbeda_dapat_menandatangani_sk_yang_sama(): void
    {
        [$letterId] = $this->createDecisionLetter();

        $this->actingAs($this->userWithNrp('SYSADMIN'))->post("/tanda-tangan/decision_letter/{$letterId}", ['typed_name' => 'Administrator Sistem']);
        $response = $this->actingAs($this->userWithNrp('2021.05.0302'))->post("/tanda-tangan/decision_letter/{$letterId}", ['typed_name' => 'Rina Marlina']);

        $response->assertSessionHas('sukses');
        $this->assertSame(2, DB::table('sig_signatures')->where('signable_id', $letterId)->count());
    }

    public function test_peran_tanpa_decision_letter_manage_ditolak(): void
    {
        [$letterId] = $this->createDecisionLetter();
        $pegawaiBiasa = $this->userWithNrp('2018.03.0142');

        $response = $this->actingAs($pegawaiBiasa)->post("/tanda-tangan/decision_letter/{$letterId}", [
            'typed_name' => 'Siti Rahmawati',
        ]);

        $response->assertForbidden();
    }

    public function test_jenis_dokumen_tidak_dikenal_ditolak(): void
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');

        $response = $this->actingAs($sysAdmin)->post('/tanda-tangan/jenis_tidak_ada/'.Uuid7::generate(), [
            'typed_name' => 'Administrator Sistem',
        ]);

        $response->assertNotFound();
    }

    /** @return array{0: string, 1: string} [letterId, skNumber] */
    private function createDecisionLetter(): array
    {
        $sysAdmin = $this->userWithNrp('SYSADMIN');
        $targetId = $this->employeeId('2021.05.0302');
        $skNumber = 'SK/TTD-UJI/'.uniqid();

        $this->actingAs($sysAdmin)->post('/sk', [
            'employee_ids' => [$targetId],
            'sk_type' => 'sanksi',
            'sk_number' => $skNumber,
            'sk_date' => '2026-01-01',
            'description' => 'Uji tanda tangan elektronik.',
        ]);

        $id = DB::table('emp_decision_letters')->where('sk_number', $skNumber)->value('id');

        return [$id, $skNumber];
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
