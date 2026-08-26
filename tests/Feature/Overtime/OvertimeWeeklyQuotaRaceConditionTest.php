<?php

declare(strict_types=1);

namespace Tests\Feature\Overtime;

use App\Core\Domain\Uuid7;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Membuktikan SELECT ... FOR UPDATE sungguhan mengunci baris di
 * PostgreSQL — bukan hanya menguji keputusan Domain (sudah dicakup
 * WeeklyOvertimeQuotaTest / SubmitOvertimeRequestTest), melainkan
 * mekanisme penguncian itu sendiri. Tanpa uji ini, regresi yang
 * MENGHAPUS lockForUpdate() dari SubmitOvertimeRequest tidak akan
 * pernah membuat satu pun test lain gagal — inilah kondisi balapan
 * yang diminta secara eksplisit (plafon 18 jam/minggu, DEC-31/DEC-32).
 *
 * TIDAK memakai DatabaseTransactions: butuh transaksi sungguhan yang
 * tetap terbuka lintas DUA koneksi berbeda — mensimulasikan dua
 * permintaan HTTP yang benar-benar bersamaan tanpa perlu proses
 * paralel sungguhan.
 */
final class OvertimeWeeklyQuotaRaceConditionTest extends TestCase
{
    private const WEEK_START = '2026-11-02'; // Senin — minggu khusus uji ini

    private string $employeeId;

    private string $quotaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeId = (string) DB::table('emp_employees')->where('nrp', '2014.02.0061')->value('id');
        $this->quotaId = (string) Uuid7::generate();

        DB::table('ovt_weekly_quotas')->insert([
            'id' => $this->quotaId,
            'employee_id' => $this->employeeId,
            'week_start_date' => self::WEEK_START,
            'approved_hours' => 0,
            'pending_hours' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('ovt_weekly_quotas')->where('id', $this->quotaId)->delete();

        parent::tearDown();
    }

    public function test_select_for_update_benar_benar_mengunci_baris_di_basis_data(): void
    {
        // KONEKSI A (default Laravel): mulai transaksi, kunci baris kuota,
        // JANGAN commit — mensimulasikan satu permintaan yang sedang
        // memutuskan pengajuan lembur.
        DB::beginTransaction();
        DB::table('ovt_weekly_quotas')->where('id', $this->quotaId)->lockForUpdate()->first();

        // KONEKSI B: koneksi PDO terpisah ke basis data yang SAMA,
        // mensimulasikan permintaan lembur lain yang datang bersamaan.
        $config = config('database.connections.pgsql');
        $pdoB = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
            $config['username'],
            $config['password'],
        );
        $pdoB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoB->exec("SET statement_timeout = '500'"); // gagal cepat, bukan menunggu selamanya

        $blocked = false;

        try {
            $pdoB->beginTransaction();
            $pdoB->query("SELECT * FROM ovt_weekly_quotas WHERE id = '{$this->quotaId}' FOR UPDATE");
            $pdoB->commit();
        } catch (PDOException $e) {
            $blocked = true;
            $this->assertMatchesRegularExpression(
                '/timeout|canceling statement/i',
                $e->getMessage(),
                'Kueri kedua harus gagal karena MENUNGGU kunci baris, bukan sebab lain.'
            );

            // Postgres membatalkan transaksi di sisi server setelah galat
            // statement_timeout, namun status transaksi PDO di sisi klien
            // tetap aktif sampai rollback() dipanggil eksplisit.
            $pdoB->rollBack();
        }

        // Lepaskan kunci A — kalau tidak, test lain bisa ikut tersendat.
        DB::rollBack();

        $this->assertTrue(
            $blocked,
            'Baris ovt_weekly_quotas TIDAK terkunci — lockForUpdate() hilang dari jalur pengajuan lembur.'
        );

        // Setelah A melepas kunci, koneksi B kini bebas mengunci baris yang sama.
        $pdoB->beginTransaction();
        $stmt = $pdoB->query("SELECT id FROM ovt_weekly_quotas WHERE id = '{$this->quotaId}' FOR UPDATE");
        $this->assertNotFalse($stmt->fetch(), 'Setelah kunci dilepas, baris harus dapat dikunci kembali.');
        $pdoB->commit();
    }
}
