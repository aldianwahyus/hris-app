<?php

declare(strict_types=1);

namespace App\Modules\Employee\Contracts;

use App\Modules\Employee\Domain\Employee;

/**
 * Satu-satunya pintu bagi modul lain untuk membaca data pegawai
 * (M-1/M-2 — ModuleBoundaryTest: modul lain hanya boleh bergantung
 * pada Contracts/, tidak pernah pada Domain/Infrastructure di sini).
 */
interface EmployeeRepository
{
    public function findByNrp(string $nrp): ?Employee;

    public function findById(string $id): ?Employee;

    /**
     * ID kantor beserta seluruh kantor turunannya (mis. cabang + KCP di
     * bawahnya), dipakai untuk lingkup OFFICE_TREE (ARCH-001 §6.2).
     *
     * @return array<int, string>
     */
    public function officeIdsInTree(string $officeId): array;

    /**
     * ID seluruh kantor bertipe tertentu (head_office|branch|sub_branch|
     * functional) — dipakai untuk lingkup Direktur Bidang/Pembina
     * (DEC-92): kewenangan mereka MEMOTONG hierarki kantor, ditentukan
     * dari tipe kantor pemohon, bukan dari pohon kantor seorang atasan.
     *
     * @param  array<int, string>  $officeTypes
     * @return array<int, string>
     */
    public function officeIdsByType(array $officeTypes): array;
}
