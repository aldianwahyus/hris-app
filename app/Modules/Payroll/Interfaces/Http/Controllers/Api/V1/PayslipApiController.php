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

        return response()->json(['data' => $slips]);
    }
}
