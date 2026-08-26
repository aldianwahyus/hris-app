<?php

declare(strict_types=1);

namespace Tests\Unit\Workflow;

use App\Shared\Workflow\Domain\SlaWindow;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * SLA adalah mitigasi risiko RA-3: persetujuan lembur terpusat pada satu
 * pejabat, sementara keterlambatan menghanguskan hak bayar pegawai.
 * Pengingat berjenjang harus benar-benar terkirim — kegagalan di sini
 * berarti pegawai kehilangan upah atas pekerjaan yang sudah dilakukan.
 */
final class SlaWindowTest extends TestCase
{
    private SlaWindow $sla;

    protected function setUp(): void
    {
        // Jendela 30 hari, sesuai batas retroaktif lembur (DEC-94)
        $this->sla = SlaWindow::days(new DateTimeImmutable('2026-08-01'), 30);
    }

    public function test_mendeteksi_pelampauan_tenggat(): void
    {
        $this->assertFalse($this->sla->isOverdue(new DateTimeImmutable('2026-08-20')));
        $this->assertTrue($this->sla->isOverdue(new DateTimeImmutable('2026-09-05')));
    }

    public function test_pengingat_pertama_terpicu_pada_ambang_h7(): void
    {
        $this->assertSame(7, $this->sla->shouldRemindNow(new DateTimeImmutable('2026-08-25')));
    }

    /**
     * REGRESI: versi awal mengembalikan null di sini lalu MELEWATKAN
     * pengingat H-3 selamanya. Pada H-6 pengingat H-7 sudah terkirim
     * namun ambang H-3 belum tercapai — sistem harus menunggu, bukan
     * menganggap seluruh pengingat selesai.
     */
    public function test_tidak_melewatkan_pengingat_berikutnya_setelah_yang_pertama_terkirim(): void
    {
        $this->assertNull(
            $this->sla->shouldRemindNow(new DateTimeImmutable('2026-08-25'), [7]),
            'Pada H-6 belum saatnya mengirim pengingat H-3.'
        );

        $this->assertSame(
            3,
            $this->sla->shouldRemindNow(new DateTimeImmutable('2026-08-29'), [7]),
            'Pada H-2 pengingat H-3 wajib terkirim.'
        );

        $this->assertNull(
            $this->sla->shouldRemindNow(new DateTimeImmutable('2026-08-29'), [7, 3]),
            'Pengingat yang sudah terkirim tidak boleh diulang.'
        );
    }

    public function test_tidak_mengingatkan_terlalu_dini(): void
    {
        $this->assertNull($this->sla->shouldRemindNow(new DateTimeImmutable('2026-08-10')));
    }
}
