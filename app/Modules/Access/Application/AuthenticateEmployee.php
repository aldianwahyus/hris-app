<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Models\User;
use App\Modules\Employee\Contracts\EmployeeRepository;
use Illuminate\Support\Facades\Hash;

/**
 * Satu-satunya jalur verifikasi kredensial NRP + kata sandi
 * (DEC-02 — HRIS sebagai identity provider tunggal).
 *
 * Dipakai oleh LoginController (sesi web) MAUPUN TokenController
 * (token API) agar logika pemeriksaan kredensial tidak terduplikasi
 * di dua tempat berbeda.
 */
final class AuthenticateEmployee
{
    public function __construct(private readonly EmployeeRepository $employees) {}

    public function verify(string $nrp, string $password): ?User
    {
        $employee = $this->employees->findByNrp($nrp);

        if ($employee === null) {
            return null;
        }

        $user = User::query()->where('employee_id', $employee->id)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
