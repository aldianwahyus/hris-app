<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Models\User;
use App\Modules\Access\Contracts\UserDirectory;
use App\Modules\Employee\Contracts\EmployeeRepository;
use Illuminate\Support\Facades\DB;

/**
 * Siapa yang berwenang memutus pengajuan cuti/lembur milik seorang
 * pegawai (ARCH-001 §6.2) — dipakai penjadwal pengingat SLA untuk
 * menentukan penerima notifikasi. BUKAN gerbang otorisasi sungguhan
 * (itu ApprovalQueueController/LeaveApprovalQueueController) — hanya
 * menentukan siapa yang DIBERI TAHU.
 *
 * Cuti dan Lembur SEKARANG memakai pola 2 TAHAP yang SAMA (koreksi
 * atas DEC-92 versi awal untuk Lembur, dan atas Cuti+HrApprover versi
 * awal — hr_approver DIHAPUS dari jalur keputusan keduanya): Atasan
 * Langsung dulu (status 'pending', OFFICE_TREE), baru Pimpinan Kantor
 * (status 'pending_pimpinan', OFFICE persis — kepala kantor pemohon,
 * bukan pohon). Tahap mana yang berlaku ditentukan dari status
 * pengajuan SPESIFIK ($documentId), bukan dari pemilik saja — satu
 * pemilik bisa punya beberapa pengajuan di tahap berbeda-beda secara
 * bersamaan.
 */
final class ResolveApprovalAudience
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly EmployeeRepository $employees,
    ) {}

    /** @return array<int, User> */
    public function forDocument(string $documentType, string $documentId, string $ownerEmployeeId): array
    {
        return match ($documentType) {
            'overtime_request' => $this->forTwoTierDocument('ovt_requests', $documentId, $ownerEmployeeId),
            default => $this->forTwoTierDocument('leave_requests', $documentId, $ownerEmployeeId),
        };
    }

    /**
     * Pola BERSAMA Cuti & Lembur — tahap ditentukan dari
     * atasan_approver_id, BUKAN dari string status (ini juga benar
     * untuk status='expired': markDocumentExpired menimpa status
     * SEBELUM baris ini dibaca, jadi 'pending'/'pending_pimpinan' sudah
     * hilang; atasan_approver_id null-atau-tidaknya tetap menunjukkan
     * tahap mana yang mandek).
     *
     * @return array<int, User>
     */
    private function forTwoTierDocument(string $table, string $documentId, string $ownerEmployeeId): array
    {
        $request = DB::table($table)->where('id', $documentId)
            ->select('status', 'atasan_approver_id')->first();

        if ($request === null || in_array($request->status, ['approved', 'rejected'], true)) {
            return []; // sudah diputus final — tidak ada lagi yang perlu diberi tahu
        }

        if ($request->atasan_approver_id === null) {
            return $this->atasanLangsungFor($ownerEmployeeId);
        }

        return $this->pimpinanKantorFor($ownerEmployeeId);
    }

    /**
     * Atasan Langsung yang kantornya (+ turunannya) mencakup kantor
     * pemilik pengajuan — logika BERSAMA Cuti dan tahap 1 Lembur,
     * jangan diduplikasi di dua tempat.
     *
     * @return array<int, User>
     */
    private function atasanLangsungFor(string $ownerEmployeeId): array
    {
        $owner = $this->employees->findById($ownerEmployeeId);

        if ($owner === null) {
            return [];
        }

        $recipients = [];
        $atasanIds = $this->users->userIdsWithRole('atasan_langsung');

        foreach (User::query()->whereIn('id', $atasanIds)->with('employee')->get() as $user) {
            $atasanOfficeId = $user->employee?->office_id;

            if ($atasanOfficeId === null) {
                continue;
            }

            if (in_array($owner->officeId, $this->employees->officeIdsInTree($atasanOfficeId), true)) {
                $recipients[$user->getKey()] = $user;
            }
        }

        return array_values($recipients);
    }

    /**
     * Pimpinan Kantor kantor pemohon PERSIS (bukan pohon) — logika
     * BERSAMA Cuti & tahap 2 Lembur.
     *
     * @return array<int, User>
     */
    private function pimpinanKantorFor(string $ownerEmployeeId): array
    {
        $ownOfficeId = DB::table('emp_employees')->where('id', $ownerEmployeeId)->value('office_id');

        if ($ownOfficeId === null) {
            return [];
        }

        $pimpinanIds = $this->users->userIdsWithRole('pimpinan_kantor');
        $recipients = [];

        foreach (User::query()->whereIn('id', $pimpinanIds)->with('employee')->get() as $user) {
            if ($user->employee?->office_id === $ownOfficeId) {
                $recipients[$user->getKey()] = $user;
            }
        }

        return array_values($recipients);
    }
}
