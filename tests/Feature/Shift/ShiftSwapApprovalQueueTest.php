<?php

declare(strict_types=1);

namespace Tests\Feature\Shift;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Notifications\RequestDecided;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tukar Shift — 1 TAHAP, Atasan Langsung SAJA (office-tree). Sengaja
 * TIDAK 2 tahap seperti Cuti/Lembur/SPPD — lihat ShiftSwapApprovalController.
 */
final class ShiftSwapApprovalQueueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_atasan_langsung_dapat_menyetujui_tukar_shift_bawahannya(): void
    {
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, atasan_langsung KC Mataram
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertRedirect(route('admin.shift-swap-queue'));
        $this->assertSame('approved', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    /**
     * Regresi: sebelumnya approve() HANYA mengubah status, TIDAK PERNAH
     * menyentuh shf_employee_assignments sama sekali — fitur tukar shift
     * tidak berdampak apa pun pada penjadwalan nyata (bug ditemukan lewat
     * audit kode, diperbaiki via ApplyShiftSwap). Kasus ini SENGAJA
     * memakai rentang penugasan yang membentang JAUH sebelum & sesudah
     * swap_date (jalur "pecah di tengah rentang" — cabang paling rumit).
     */
    public function test_persetujuan_benar_benar_menukar_penugasan_shift_kedua_pegawai_pada_tanggal_itu(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        $hendraId = $this->employeeId('2017.11.0119');

        $pagiId = $this->insertPattern('Pagi');
        $malamId = $this->insertPattern('Malam');

        $swapDate = now()->addDays(3)->startOfDay();
        $before = $swapDate->copy()->subDay()->format('Y-m-d');
        $after = $swapDate->copy()->addDay()->format('Y-m-d');
        $rangeFrom = $swapDate->copy()->subDays(10)->format('Y-m-d');
        $rangeTo = $swapDate->copy()->addDays(10)->format('Y-m-d');

        $this->insertAssignment($sitiId, $pagiId, $rangeFrom, $rangeTo);
        $this->insertAssignment($hendraId, $malamId, $rangeFrom, $rangeTo);

        $requestId = (string) Uuid7::generate();
        DB::table('shf_swap_requests')->insert([
            'id' => $requestId,
            'request_number' => 'TS/TEST/'.uniqid(),
            'requesting_employee_id' => $sitiId,
            'counterpart_employee_id' => $hendraId,
            'swap_date' => $swapDate->format('Y-m-d'),
            'requesting_original_pattern_id' => $pagiId,
            'counterpart_original_pattern_id' => $malamId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->userWithNrp('2015.07.0088')) // Ahmad, atasan_langsung KC Mataram
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertRedirect(route('admin.shift-swap-queue'));

        $this->assertSame($malamId, $this->patternIdOn($sitiId, $swapDate->format('Y-m-d')), 'Siti harus dapat pola Hendra (Malam) PERSIS pada swap_date.');
        $this->assertSame($pagiId, $this->patternIdOn($hendraId, $swapDate->format('Y-m-d')), 'Hendra harus dapat pola Siti (Pagi) PERSIS pada swap_date.');

        $this->assertSame($pagiId, $this->patternIdOn($sitiId, $before), 'Sehari sebelum swap_date, Siti harus TETAP pola aslinya.');
        $this->assertSame($pagiId, $this->patternIdOn($sitiId, $after), 'Sehari sesudah swap_date, Siti harus TETAP pola aslinya.');
        $this->assertSame($malamId, $this->patternIdOn($hendraId, $before), 'Sehari sebelum swap_date, Hendra harus TETAP pola aslinya.');
        $this->assertSame($malamId, $this->patternIdOn($hendraId, $after), 'Sehari sesudah swap_date, Hendra harus TETAP pola aslinya.');
    }

    public function test_penolakan_tidak_menukar_penugasan_apa_pun(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        $hendraId = $this->employeeId('2017.11.0119');

        $pagiId = $this->insertPattern('Pagi');
        $malamId = $this->insertPattern('Malam');

        $swapDate = now()->addDays(3);
        $this->insertAssignment($sitiId, $pagiId, $swapDate->copy()->subDays(10)->format('Y-m-d'), $swapDate->copy()->addDays(10)->format('Y-m-d'));
        $this->insertAssignment($hendraId, $malamId, $swapDate->copy()->subDays(10)->format('Y-m-d'), $swapDate->copy()->addDays(10)->format('Y-m-d'));

        $requestId = (string) Uuid7::generate();
        DB::table('shf_swap_requests')->insert([
            'id' => $requestId,
            'request_number' => 'TS/TEST/'.uniqid(),
            'requesting_employee_id' => $sitiId,
            'counterpart_employee_id' => $hendraId,
            'swap_date' => $swapDate->format('Y-m-d'),
            'requesting_original_pattern_id' => $pagiId,
            'counterpart_original_pattern_id' => $malamId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/tukar-shift/{$requestId}/tolak");

        $this->assertSame($pagiId, $this->patternIdOn($sitiId, $swapDate->format('Y-m-d')));
        $this->assertSame($malamId, $this->patternIdOn($hendraId, $swapDate->format('Y-m-d')));
    }

    public function test_atasan_kantor_lain_tidak_dapat_melihat_atau_memutus(): void
    {
        // Pemohon di KC Mataram, aktor Nur Aisyah HANYA pimpinan_kantor
        // KP (bukan atasan_langsung kantor mana pun) setelah dicabut.
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));
        $dewi = $this->userWithNrp('2019.09.0177'); // KC Selong — bukan pohon kantor Mataram, bukan atasan_langsung
        $this->grantRole($dewi, 'atasan_langsung');

        $response = $this->actingAs($dewi)->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_pemohon_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        $requestId = $this->insertSwapRequest($sitiId, $this->employeeId('2017.11.0119'));
        $this->grantRole($this->userWithNrp('2018.03.0142'), 'atasan_langsung');

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertForbidden();
    }

    public function test_rekan_yang_dituju_tidak_dapat_menyetujui_meski_atasan_langsung(): void
    {
        $hendraId = $this->employeeId('2017.11.0119');
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $hendraId);
        $this->grantRole($this->userWithNrp('2017.11.0119'), 'atasan_langsung');

        $response = $this->actingAs($this->userWithNrp('2017.11.0119'))
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        $response->assertForbidden();
        $this->assertSame('pending', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_peran_lain_ditolak_dari_antrean(): void
    {
        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/persetujuan/tukar-shift');

        $response->assertForbidden();
    }

    /**
     * Celah ditemukan lewat evaluasi PM/client (2026-08-27) — pola SAMA
     * PERSIS LeaveApprovalQueueScopeTest. Tukar Shift SATU tahap: setiap
     * keputusan selalu final, notifikasi SELALU terkirim.
     */
    public function test_penolakan_menyimpan_alasan_dan_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/tukar-shift/{$requestId}/tolak", ['catatan' => 'Rekan yang dituju sudah penuh jadwalnya.']);

        $response->assertRedirect(route('admin.shift-swap-queue'));

        $row = DB::table('shf_swap_requests')->where('id', $requestId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Rekan yang dituju sudah penuh jadwalnya.', $row->decision_note);

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === false && $n->reason === 'Rekan yang dituju sudah penuh jadwalnya.',
        );
    }

    public function test_setuju_mengirim_notifikasi_ke_pemohon(): void
    {
        Notification::fake();

        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));

        $this->actingAs($this->userWithNrp('2015.07.0088'))
            ->post("/persetujuan/tukar-shift/{$requestId}/setujui");

        Notification::assertSentTo(
            $this->userWithNrp('2018.03.0142'),
            fn (RequestDecided $n) => $n->approved === true,
        );
    }

    public function test_batal_saat_pending_berhasil(): void
    {
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/tukar-shift/{$requestId}/batal");

        $response->assertRedirect();
        $this->assertSame('cancelled', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_batal_gagal_setelah_diputus(): void
    {
        $requestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));
        DB::table('shf_swap_requests')->where('id', $requestId)->update(['status' => 'approved']);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))
            ->post("/tukar-shift/{$requestId}/batal");

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertSame('approved', DB::table('shf_swap_requests')->where('id', $requestId)->value('status'));
    }

    public function test_riwayat_hanya_menampilkan_pengajuan_milik_sendiri(): void
    {
        $sitiRequestId = $this->insertSwapRequest($this->employeeId('2018.03.0142'), $this->employeeId('2017.11.0119'));
        $sitiRow = DB::table('shf_swap_requests')->where('id', $sitiRequestId)->first();
        $this->assertNotNull($sitiRow);

        $dewiRequestId = $this->insertSwapRequest($this->employeeId('2019.09.0177'), $this->employeeId('2017.11.0119'));
        $dewiRow = DB::table('shf_swap_requests')->where('id', $dewiRequestId)->first();
        $this->assertNotNull($dewiRow);

        $response = $this->actingAs($this->userWithNrp('2018.03.0142'))->get('/tukar-shift/riwayat');

        $response->assertOk();
        $response->assertSee($sitiRow->request_number);
        $response->assertDontSee($dewiRow->request_number);
    }

    private function insertSwapRequest(string $requestingEmployeeId, string $counterpartEmployeeId): string
    {
        $patternId = (string) Uuid7::generate();
        DB::table('shf_shift_patterns')->insert([
            'id' => $patternId,
            'code' => 'PAGI-'.uniqid(),
            'name' => 'Shift Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        $id = (string) Uuid7::generate();

        DB::table('shf_swap_requests')->insert([
            'id' => $id,
            'request_number' => 'TS/TEST/'.uniqid(),
            'requesting_employee_id' => $requestingEmployeeId,
            'counterpart_employee_id' => $counterpartEmployeeId,
            'swap_date' => now()->addDays(3)->format('Y-m-d'),
            'requesting_original_pattern_id' => $patternId,
            'counterpart_original_pattern_id' => $patternId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function grantRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        $alreadyHas = DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $roleId)->exists();

        if (! $alreadyHas) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        }
    }

    private function insertPattern(string $name): string
    {
        $id = (string) Uuid7::generate();

        DB::table('shf_shift_patterns')->insert([
            'id' => $id,
            'code' => strtoupper($name).'-'.uniqid(),
            'name' => $name,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);

        return $id;
    }

    private function insertAssignment(string $employeeId, string $patternId, string $from, string $to): void
    {
        DB::table('shf_employee_assignments')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'shift_pattern_id' => $patternId,
            'effective_from' => $from,
            'effective_to' => $to,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }

    private function patternIdOn(string $employeeId, string $date): ?string
    {
        return DB::table('shf_employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->value('shift_pattern_id');
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
