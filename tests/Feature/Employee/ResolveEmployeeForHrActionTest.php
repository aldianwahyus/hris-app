<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Modules\Employee\Application\ResolveEmployeeForHrAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Gerbang lingkup bersama dipakai 5 controller Kelompok A (Data
 * Pasangan & Anak, Riwayat Kerja, Sanksi, Riwayat Kesehatan) — diuji
 * TERSENDIRI di sini sebelum dipakai controller manapun.
 *
 * handle() menerima peran/kantor aktor sebagai PARAMETER (bukan
 * inject Access\Contracts\CurrentActor) — lihat docblock kelas untuk
 * alasan (mencegah ketergantungan melingkar Access⇄Employee) — jadi
 * tidak perlu fake CurrentActor di sini, cukup array biasa.
 */
final class ResolveEmployeeForHrActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_berhasil_untuk_pegawai_kantornya_sendiri(): void
    {
        $officeId = $this->employeeOfficeId('2018.03.0142'); // KC Mataram
        $targetId = $this->employeeId('2018.03.0142');

        $employee = $this->resolver()->handle($targetId, ['hr_admin'], $officeId);

        // Cast ke array — properti object bebas dari DB::table()->first()
        // tidak terkena celah PHPStan pada akses properti lintas method.
        $this->assertSame($targetId, ((array) $employee)['id']);
    }

    public function test_hr_admin_ditolak_untuk_pegawai_kantor_lain(): void
    {
        $rinaOfficeId = $this->employeeOfficeId('2021.05.0302'); // KCP Gerung
        $sitiId = $this->employeeId('2018.03.0142'); // KC Mataram

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('di luar lingkup');

        $this->resolver()->handle($sitiId, ['hr_admin'], $rinaOfficeId);
    }

    public function test_hr_approver_berhasil_untuk_kantor_mana_pun(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $employee = $this->resolver()->handle($sitiId, ['hr_approver'], null);

        $this->assertSame($sitiId, ((array) $employee)['id']);
    }

    public function test_sysadmin_berhasil_untuk_kantor_mana_pun(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $employee = $this->resolver()->handle($sitiId, ['system_admin'], null);

        $this->assertSame($sitiId, ((array) $employee)['id']);
    }

    public function test_pegawai_tidak_ditemukan_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver()->handle('00000000-0000-7000-8000-000000000000', ['hr_approver'], null);
    }

    private function resolver(): ResolveEmployeeForHrAction
    {
        return new ResolveEmployeeForHrAction;
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function employeeOfficeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('office_id');
    }
}
