<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CWE-525 / OWASP A01/A04 — halaman terautentikasi TIDAK boleh
 * tersimpan di cache lokal browser (termasuk bfcache), sehingga
 * tombol "kembali" setelah logout tidak menampilkan data HRIS
 * rahasia dari render lama. LogoutController SUDAH benar menghancurkan
 * sesi server-side; tes ini memastikan sisi klien (header respons)
 * juga tidak mengizinkan penyimpanan salinan halaman.
 */
final class BackButtonCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_halaman_terautentikasi_mengirim_no_store(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/beranda');

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_halaman_masuk_juga_mengirim_no_store(): void
    {
        $response = $this->get('/masuk');

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = DB::table('emp_employees')->where('nrp', $nrp)->value('id');

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
