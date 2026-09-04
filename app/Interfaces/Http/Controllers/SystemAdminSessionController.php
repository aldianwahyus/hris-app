<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manajemen Sesi Aktif (Fase 2, evaluasi PM/client 2026-09-03) — lihat
 * SEMUA sesi login bank-wide + cabut paksa. Lingkup TEKNIS (akun
 * login), pola SAMA `SystemAdminUserController::resetPassword()`:
 * kunci gerbang keamanan akun, TETAP hardcode `role:system_admin`
 * (bukan permission dinamis) — melihat IP/perangkat orang lain dan
 * bisa memaksa logout paksa adalah kelas kewenangan yang SAMA dengan
 * reset kata sandi/2FA, bukan sekadar "lihat data".
 */
final class SystemAdminSessionController extends Controller
{
    public function index(): View
    {
        $sessions = DB::table('sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('emp_employees as e', 'e.id', '=', 'u.employee_id')
            ->select('s.id', 's.ip_address', 's.user_agent', 's.last_activity', 'u.name as user_name', 'e.nrp')
            ->orderByDesc('s.last_activity')
            ->get()
            ->map(function ($row) {
                $row->last_activity_human = Carbon::createFromTimestamp($row->last_activity)->diffForHumans();

                return $row;
            });

        return view('admin.sessions', ['sessions' => $sessions]);
    }

    public function revoke(string $id): RedirectResponse
    {
        DB::table('sessions')->where('id', $id)->delete();

        return redirect()->route('sysadmin.sessions.index')->with('sukses', 'Sesi berhasil dicabut.');
    }
}
