<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Daftar menu Aplikasi Mobile yang boleh tampil — dikendalikan SYSADMIN/
 * Admin HC lewat MobileMenuSettingsController (web). Bentuk respons
 * {key: boolean} sengaja rata/sederhana — klien mobile tinggal
 * mengecek `config[key] !== false` (bawaan aman: menu yang TIDAK
 * dikenal/belum disemai dianggap TETAP TAMPIL, bukan tersembunyi diam-
 * diam — lihat MobileMenuContext.tsx).
 *
 * TIDAK di-cache di server: tabelnya cuma 7 baris (murah dikueri tiap
 * permintaan) dan klien mobile mengambil ulang setiap kali dibuka/
 * kembali aktif — cache di sini hanya akan menambah jeda perubahan
 * saklar admin terlihat, tanpa manfaat performa berarti.
 */
final class MobileMenuApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $config = DB::table('mobile_menu_items')
            ->pluck('is_enabled', 'key')
            ->map(fn ($enabled) => (bool) $enabled);

        return response()->json(['data' => $config]);
    }
}
