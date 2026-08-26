<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use Illuminate\Support\Facades\DB;

/**
 * Gerbang lingkup BERSAMA untuk aksi HR-only atas data riwayat pegawai
 * (Data Pasangan & Anak, Riwayat Kerja, Sanksi, Riwayat Kesehatan —
 * lihat migrasi 2026_08_23_000001) — dipakai 5 controller sekaligus
 * (bukan diduplikasi 5×): hr_admin HANYA pegawai kantornya sendiri,
 * hr_approver/system_admin bank-wide.
 *
 * MURNI pemeriksaan wewenang tulis-langsung (bukan maker-checker) —
 * TIDAK terkait AccessPolicy/OrganizationalScope yang dipakai untuk
 * antrean persetujuan.
 *
 * SENGAJA menerima peran/kantor aktor sebagai PARAMETER (bukan
 * constructor-inject Access\Contracts\CurrentActor langsung) —
 * Access sudah bergantung pada Employee\Contracts\EmployeeRepository
 * (ResolveApprovalAudience/AuthenticateEmployee/LoginController),
 * jadi arah sebaliknya di sini akan membentuk ketergantungan melingkar
 * Access⇄Employee (ModuleBoundaryTest). Pola sama seperti
 * SubmitLeaveRequest/SubmitEmployeeProfileChange: controller pemanggil
 * (di app/Interfaces/Http/Controllers, DI LUAR pohon modul) yang
 * menyelesaikan CurrentActor dan mengoper nilainya sebagai parameter.
 */
final class ResolveEmployeeForHrAction
{
    /** @param array<int, string> $actorRoles */
    public function handle(string $employeeId, array $actorRoles, ?string $actorOfficeId): object
    {
        $employee = DB::table('emp_employees')->where('id', $employeeId)->first();

        abort_if($employee === null, 404);

        $bankWide = array_intersect(['hr_approver', 'system_admin'], $actorRoles) !== [];

        if ($bankWide) {
            return $employee;
        }

        abort_unless(
            in_array('hr_admin', $actorRoles, true) && $employee->office_id === $actorOfficeId,
            403,
            'Pegawai ini di luar lingkup Anda.'
        );

        return $employee;
    }
}
