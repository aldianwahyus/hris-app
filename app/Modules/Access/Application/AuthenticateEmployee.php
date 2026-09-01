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
    // Hash bcrypt tetap (bukan rahasia — tidak pernah dicocokkan dengan
    // kata sandi sungguhan siapa pun) dipakai HANYA untuk menyamakan
    // waktu respons saat NRP tidak dikenal/user tidak ada, supaya
    // Hash::check() (lambat, disengaja) TETAP dijalankan di jalur itu —
    // tanpa ini, NRP tak dikenal akan merespons jauh lebih cepat
    // daripada NRP dikenal + kata sandi salah, kanal timing untuk
    // menebak NRP mana yang terdaftar (celah pencacahan akun).
    private const DUMMY_HASH = '$2y$12$vj5pH1T.6j5X1fqKGwcwO.t22J8M0Hgq4q9rNkFP.xCGVOO5I39bS';

    public function __construct(private readonly EmployeeRepository $employees) {}

    public function verify(string $nrp, string $password): ?User
    {
        $employee = $this->employees->findByNrp($nrp);

        if ($employee === null) {
            Hash::check($password, self::DUMMY_HASH);

            return null;
        }

        $user = User::query()->where('employee_id', $employee->id)->first();

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
