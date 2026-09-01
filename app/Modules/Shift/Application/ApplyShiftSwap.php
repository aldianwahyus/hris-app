<?php

declare(strict_types=1);

namespace App\Modules\Shift\Application;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menukar pola shift KEDUA pegawai untuk PERSIS satu tanggal (swap_date),
 * setelah tukar shift disetujui — sebelumnya persetujuan HANYA mengubah
 * shf_swap_requests.status, tidak pernah menyentuh shf_employee_assignments
 * sama sekali, sehingga fitur ini tidak berdampak apa pun pada penjadwalan
 * nyata (bug ditemukan lewat audit kode).
 *
 * shf_employee_assignments berbasis RENTANG tanggal (effective_from/
 * effective_to), bukan satu baris per hari — jadi "menukar satu hari"
 * berarti memecah rentang yang menaungi swap_date: susutkan rentang asli
 * supaya TIDAK lagi mencakup swap_date, lalu sisipkan baris satu-hari
 * berisi pola pegawai lain untuk tanggal itu saja.
 */
final class ApplyShiftSwap
{
    public function handle(
        string $requestingEmployeeId,
        string $counterpartEmployeeId,
        DateTimeImmutable $swapDate,
        string $requestingOriginalPatternId,
        string $counterpartOriginalPatternId,
        ?string $actorId,
        DateTimeImmutable $now,
    ): void {
        DB::transaction(function () use (
            $requestingEmployeeId, $counterpartEmployeeId, $swapDate,
            $requestingOriginalPatternId, $counterpartOriginalPatternId, $actorId, $now,
        ) {
            // Pegawai A menerima pola ASLI milik pegawai B, dan sebaliknya.
            $this->swapOneDay($requestingEmployeeId, $swapDate, $counterpartOriginalPatternId, $actorId, $now);
            $this->swapOneDay($counterpartEmployeeId, $swapDate, $requestingOriginalPatternId, $actorId, $now);
        });
    }

    private function swapOneDay(string $employeeId, DateTimeImmutable $swapDate, string $newPatternId, ?string $actorId, DateTimeImmutable $now): void
    {
        $dateString = $swapDate->format('Y-m-d');

        $row = DB::table('shf_employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $dateString)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dateString))
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            // Penugasan pegawai ini berubah sejak pengajuan dibuat (mis.
            // admin menugaskan ulang) — jangan menimpa keadaan yang tidak
            // dikenal, biarkan penugasan terkini apa adanya.
            return;
        }

        $dayBefore = $swapDate->modify('-1 day')->format('Y-m-d');
        $dayAfter = $swapDate->modify('+1 day')->format('Y-m-d');

        if ($row->effective_from === $dateString && $row->effective_to === $dateString) {
            // Rentang persis satu hari — tinggal ganti pola pada baris ini,
            // tidak perlu memecah apa pun.
            DB::table('shf_employee_assignments')->where('id', $row->id)->update([
                'shift_pattern_id' => $newPatternId,
                'updated_at' => $now,
                'version' => $row->version + 1,
            ]);

            return;
        }

        if ($row->effective_from === $dateString) {
            // swap_date ada di AWAL rentang — susutkan dari depan.
            DB::table('shf_employee_assignments')->where('id', $row->id)->update([
                'effective_from' => $dayAfter,
                'updated_at' => $now,
                'version' => $row->version + 1,
            ]);
        } elseif ($row->effective_to === $dateString) {
            // swap_date ada di AKHIR rentang — susutkan dari belakang.
            DB::table('shf_employee_assignments')->where('id', $row->id)->update([
                'effective_to' => $dayBefore,
                'updated_at' => $now,
                'version' => $row->version + 1,
            ]);
        } else {
            // swap_date ada di TENGAH rentang — pecah jadi baris sebelum
            // (baris asli, disusutkan) dan baris sesudah (baru, pola sama).
            DB::table('shf_employee_assignments')->where('id', $row->id)->update([
                'effective_to' => $dayBefore,
                'updated_at' => $now,
                'version' => $row->version + 1,
            ]);

            DB::table('shf_employee_assignments')->insert([
                'id' => (string) Uuid7::generate(),
                'employee_id' => $employeeId,
                'shift_pattern_id' => $row->shift_pattern_id,
                'effective_from' => $dayAfter,
                'effective_to' => $row->effective_to,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);
        }

        // Baris satu-hari untuk swap_date itu sendiri, berisi pola pegawai lain.
        DB::table('shf_employee_assignments')->insert([
            'id' => (string) Uuid7::generate(),
            'employee_id' => $employeeId,
            'shift_pattern_id' => $newPatternId,
            'effective_from' => $dateString,
            'effective_to' => $dateString,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);
    }
}
