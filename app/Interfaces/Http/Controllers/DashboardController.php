<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Beranda pegawai (Employee Self Service).
 *
 * Menampilkan saldo cuti sebagai TIGA KANTONG TERPISAH, bukan satu angka.
 * Ini terjemahan langsung aturan BPP: hanya jatah tahun berjalan yang
 * memberi hak bekal cuti, sedangkan sisa tahun lalu tidak. Menggabungkan
 * keduanya membuat pegawai salah memperkirakan haknya.
 *
 * Lingkup SELF (ARCH-001 §6.2) — pemilik selalu dapat melihat datanya
 * sendiri, terlepas dari peran apa pun yang dipegangnya. Karena itu
 * layar ini tidak memerlukan pemeriksaan Role/Scope tambahan: identitas
 * pegawai diambil langsung dari akun yang sedang masuk.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $employeeId = $request->user()?->employee_id;

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $employee = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->select(
                'e.id', 'e.nrp', 'e.full_name', 'e.person_grade', 'e.permanent_date',
                'p.name as position_name', 'p.eligible_overtime_regular',
                'o.name as office_name', 'o.timezone'
            )
            ->where('e.id', $employeeId)
            ->first();

        abort_if($employee === null, 404, 'Data pegawai untuk akun ini tidak ditemukan.');

        $balances = DB::table('leave_balances')
            ->where('employee_id', $employee->id)
            ->where('year', (int) date('Y'))
            ->orderBy('consumption_order')
            ->get();

        $requests = DB::table('leave_requests')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        $overtime = DB::table('ovt_requests')
            ->where('employee_id', $employee->id)
            ->orderByDesc('work_date')
            ->limit(3)
            ->get();

        // Hak cuti besar timbul setelah 6 tahun berturut-turut sejak
        // pengangkatan tetap — bukan sejak mulai bekerja.
        $longLeaveYear = $employee->permanent_date !== null
            ? (int) date('Y', strtotime($employee->permanent_date)) + 6
            : null;

        return view('ess.dashboard', compact(
            'employee', 'balances', 'requests', 'overtime', 'longLeaveYear'
        ));
    }
}
