<?php

declare(strict_types=1);

namespace Tests\Feature\Izin;

use App\Modules\Izin\Application\SubmitIzinRequest;
use App\Modules\Izin\Domain\IzinCategory;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Izin Tidak Masuk Bekerja — TERPISAH dari Cuti: leave_balances TIDAK
 * PERNAH disentuh oleh modul ini (beda paling penting dari SubmitLeaveRequest).
 */
final class SubmitIzinRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pengajuan_berhasil_dan_terhubung_ke_workflow_serta_audit(): void
    {
        $employeeId = $this->employeeId('2018.03.0142'); // Siti, KC Mataram

        // Siti SUDAH punya histori cuti dari data contoh Wave 1 — jangan
        // asumsikan saldo awal 0, tangkap nilai SEBELUM lalu buktikan
        // TIDAK BERUBAH setelah pengajuan izin (bukan "harus 0").
        $usedDaysBefore = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');

        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::KeperluanKeluarga,
            startDate: new DateTimeImmutable('2026-09-01'), // Selasa
            endDate: new DateTimeImmutable('2026-09-01'),
            reason: 'Uji coba',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: true,
        );

        $row = DB::table('izin_requests')->where('request_number', $requestNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertSame('keperluan_keluarga', $row->category);
        $this->assertEquals(1.0, (float) $row->total_days);
        $this->assertNotNull($row->wf_instance_id);

        $instance = DB::table('wf_instances')->where('id', $row->wf_instance_id)->first();
        $this->assertSame('izin_request', $instance->document_type);

        $audit = DB::table('aud_change_logs')->where('auditable_id', $row->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('submitted', $audit->action);

        // Bukti eksplisit modul ini TIDAK memotong saldo cuti sama sekali.
        $usedDaysAfter = (float) DB::table('leave_balances')
            ->where('employee_id', $employeeId)->where('year', 2026)->where('bucket_type', 'current_year')
            ->value('used_days');
        $this->assertEquals($usedDaysBefore, $usedDaysAfter, 'Izin tidak boleh memotong saldo Cuti Tahunan.');
    }

    public function test_hari_dihitung_hari_kerja_mengecualikan_akhir_pekan(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        // 2026-09-01 (Selasa) s.d. 2026-09-07 (Senin) = 5 hari kerja
        // (akhir pekan 09-05/09-06 dikecualikan) — pola sama Cuti.
        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Lainnya,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-07'),
            reason: 'Uji hari kerja',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: true,
        );

        $row = DB::table('izin_requests')->where('request_number', $requestNumber)->first();
        $this->assertEquals(5.0, (float) $row->total_days);
    }

    public function test_kategori_sakit_tanpa_lampiran_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        $this->expectException(InvalidArgumentException::class);

        $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Sakit,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-01'),
            reason: 'Demam',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: true,
        );
    }

    public function test_kategori_sakit_dengan_lampiran_berhasil(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Sakit,
            startDate: new DateTimeImmutable('2026-09-01'),
            endDate: new DateTimeImmutable('2026-09-01'),
            reason: 'Demam',
            attachmentPath: 'izin/surat-dokter-test.jpg',
            attachmentOriginalName: 'surat-dokter.jpg',
            actor: $this->actorFor($employeeId),
            isAdminHc: true,
        );

        $row = DB::table('izin_requests')->where('request_number', $requestNumber)->first();
        $this->assertSame('izin/surat-dokter-test.jpg', $row->attachment_path);
    }

    public function test_tanggal_selesai_sebelum_mulai_ditolak(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        $this->expectException(InvalidArgumentException::class);

        $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Lainnya,
            startDate: new DateTimeImmutable('2026-09-10'),
            endDate: new DateTimeImmutable('2026-09-05'),
            reason: 'Uji tanggal terbalik',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: true,
        );
    }

    public function test_pegawai_biasa_wajib_tanggal_mulai_hari_ini(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tanggal mulai izin wajib hari ini');

        // Bug ditemukan lewat evaluasi PM/client (2026-09-01): tanggal
        // literal tetap ('2026-09-01') berhenti "bukan hari ini" begitu
        // tanggal sungguhan mencapainya. Perbaikan PERTAMA (memakai
        // 'tomorrow' pada zona waktu default PHP/UTC) TERNYATA masih
        // rapuh — office_timezone pegawai ini (Asia/Makassar, UTC+8)
        // sudah lebih dulu masuk hari berikutnya daripada UTC pada jam
        // tertentu, sehingga "besok" versi UTC bisa kebetulan SAMA
        // dengan "hari ini" versi kantor. Dihitung relatif TERHADAP zona
        // waktu kantor itu sendiri (pola sama test di bawah ini) supaya
        // benar-benar dijamin beda, bukan cuma "biasanya beda".
        $officeTimezone = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $employeeId)
            ->value('o.timezone') ?? 'Asia/Makassar';
        $besok = (new DateTimeImmutable('today', new DateTimeZone($officeTimezone)))->modify('+1 day');

        $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Lainnya,
            startDate: $besok, // bukan hari ini
            endDate: $besok,
            reason: 'Uji back date',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: false,
        );
    }

    public function test_pegawai_biasa_berhasil_jika_tanggal_mulai_hari_ini(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');
        $officeTimezone = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $employeeId)
            ->value('o.timezone') ?? 'Asia/Makassar';
        $today = new DateTimeImmutable('today', new DateTimeZone($officeTimezone));

        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Lainnya,
            startDate: $today,
            endDate: $today->modify('+3 days'),
            reason: 'Uji tanggal hari ini',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: false,
        );

        $row = DB::table('izin_requests')->where('request_number', $requestNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame($today->format('Y-m-d'), $row->start_date);
    }

    public function test_admin_hc_dikecualikan_dari_pembatasan_tanggal_mulai(): void
    {
        $employeeId = $this->employeeId('2018.03.0142');

        $requestNumber = $this->submit()->handle(
            employeeId: $employeeId,
            category: IzinCategory::Lainnya,
            startDate: new DateTimeImmutable('2026-01-05'), // tanggal lampau, bukan hari ini
            endDate: new DateTimeImmutable('2026-01-06'),
            reason: 'Diajukan Admin HC untuk pegawai',
            attachmentPath: null,
            attachmentOriginalName: null,
            actor: $this->actorFor($employeeId),
            isAdminHc: true,
        );

        $row = DB::table('izin_requests')->where('request_number', $requestNumber)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-01-05', $row->start_date);
    }

    private function submit(): SubmitIzinRequest
    {
        return app(SubmitIzinRequest::class);
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function actorFor(string $employeeId): AuditActor
    {
        return new AuditActor(actorId: $employeeId, actorRole: 'pegawai');
    }
}
