<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ESS Mobile (Fase 2) — cermin AssetAssignmentController::mine(), BACA
 * SAJA: aset yang sedang dipegang pegawai yang login. Tidak ada
 * Application layer terpisah (query langsung, pola SAMA jalur web).
 */
final class AssetApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $assignments = DB::table('ast_assignments as asg')
            ->join('ast_assets as a', 'a.id', '=', 'asg.asset_id')
            ->where('asg.employee_id', $user->employee_id)
            ->whereNull('asg.returned_at')
            ->select('a.asset_code', 'a.name', 'a.category', 'a.brand_model', 'a.serial_number', 'asg.assigned_at')
            ->orderBy('asg.assigned_at')
            ->get();

        return response()->json(['data' => $assignments]);
    }
}
