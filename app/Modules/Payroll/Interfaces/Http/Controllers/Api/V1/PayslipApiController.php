<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ESS Mobile (TOR Fase I) — cermin PayslipController::index(). Unduh
 * PDF tetap lewat routes/web.php (payslip.download, memerlukan sesi
 * web) — di sini hanya daftar + id untuk ditautkan klien mobile.
 *
 * take_home_partial_cents TIDAK PERNAH memutasi lewat potongan/tambahan
 * ad-hoc (lihat RecordPayslipDeduction/RecordPayslipAddition) — respons
 * ini menyertakan deductions/additions MENTAH per slip supaya klien
 * mobile bisa menghitung THP yang benar, PERSIS seperti PayslipController::
 * index() (web) dan download() (PDF) — sebelumnya endpoint ini hanya
 * mengembalikan take_home_partial_cents mentah, membuat aplikasi mobile
 * menampilkan THP yang BERBEDA (lebih besar, salah) dari web/PDF untuk
 * slip yang sama (bug ditemukan lewat audit kode, konsisten dengan
 * perbaikan PayslipController::index()).
 */
final class PayslipApiController
{
    public function index(Request $request): JsonResponse
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

        $slipIds = $slips->pluck('id');

        $deductionsByPayslip = DB::table('pay_payslip_deductions')
            ->whereIn('payslip_id', $slipIds)
            ->get()
            ->groupBy('payslip_id');

        $additionsByPayslip = DB::table('pay_payslip_additions')
            ->whereIn('payslip_id', $slipIds)
            ->get()
            ->groupBy('payslip_id');

        $slips = $slips->map(function ($slip) use ($deductionsByPayslip, $additionsByPayslip) {
            $deductions = $deductionsByPayslip->get($slip->id, collect());
            $additions = $additionsByPayslip->get($slip->id, collect());

            $slip->deductions = $deductions->values();
            $slip->additions = $additions->values();
            $slip->take_home_cents = $slip->take_home_partial_cents
                - $deductions->sum('amount_cents')
                + $additions->sum('amount_cents');

            return $slip;
        });

        return response()->json(['data' => $slips]);
    }
}
