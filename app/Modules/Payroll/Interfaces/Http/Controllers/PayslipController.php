<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Slip gaji ESS — lingkup SELF, hanya slip dari payroll run yang
 * SUDAH DISETUJUI (draf tidak pernah terlihat pegawai).
 */
final class PayslipController
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $slips = DB::table('pay_payslips as s')
            ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->where('s.employee_id', $user->employee_id)
            ->where('r.status', 'approved')
            ->select('s.*', 'r.period')
            ->orderByDesc('r.period')
            ->get();

        return view('payroll.payslip', compact('slips'));
    }

    /**
     * Unduh satu slip sebagai PDF — lingkup SELF yang SAMA dengan
     * index(): hanya slip milik sendiri dari run yang sudah disetujui.
     * Daftar pending_components WAJIB tetap tampil (properti inti
     * domain PayslipComponents, bukan detail tampilan yang boleh
     * disembunyikan pada unduhan).
     */
    public function download(Request $request, string $id): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        // approved_by SELALU terisi untuk run berstatus 'approved' (lihat
        // DecidePayrollRun::approve()) — dipakai sebagai tanda tangan
        // "Pimpinan Divisi HC" pada PDF, bukan nama tetap yang dihardcode,
        // karena mencerminkan siapa SUNGGUHAN menyetujui run slip ini.
        $slip = DB::table('pay_payslips as s')
            ->join('pay_payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->join('emp_employees as e', 'e.id', '=', 's.employee_id')
            ->join('md_positions as op', 'op.id', '=', 'e.position_id')
            ->join('md_offices as oo', 'oo.id', '=', 'e.office_id')
            ->join('emp_employees as approver', 'approver.id', '=', 'r.approved_by')
            ->join('md_positions as ap', 'ap.id', '=', 'approver.position_id')
            ->where('s.id', $id)
            ->where('s.employee_id', $user->employee_id)
            ->where('r.status', 'approved')
            ->select(
                's.*', 'r.period', 'e.full_name', 'e.nrp', 'e.nomor_simpeda',
                'op.name as jabatan_name', 'oo.name as unit_kerja_name',
                'approver.full_name as approver_name', 'ap.name as approver_position',
            )
            ->first();

        abort_if($slip === null, 404);

        // Potongan/tambahan ad-hoc TIDAK PERNAH memutasi take_home_partial_cents
        // (lihat RecordPayslipDeduction/RecordPayslipAddition) — dikelompokkan
        // per jenis di sini, JUMLAH PENGHASILAN/POTONGAN/THP dihitung di
        // template dari kombinasi kolom tetap + baris-baris ini.
        $deductionsByType = DB::table('pay_payslip_deductions')
            ->where('payslip_id', $id)
            ->orderBy('created_at')
            ->get()
            ->groupBy('deduction_type');

        $additionsByType = DB::table('pay_payslip_additions')
            ->where('payslip_id', $id)
            ->orderBy('created_at')
            ->get()
            ->groupBy('addition_type');

        $pdf = Pdf::loadView('payroll.payslip-pdf', [
            's' => $slip,
            'deductionsByType' => $deductionsByType,
            'additionsByType' => $additionsByType,
        ]);

        return $pdf->download("slip-gaji-{$slip->period}.pdf");
    }
}
