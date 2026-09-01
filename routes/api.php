<?php

declare(strict_types=1);

use App\Interfaces\Http\Controllers\Api\V1\MobileMenuApiController;
use App\Interfaces\Http\Controllers\Api\V1\NotificationApiController;
use App\Modules\Access\Interfaces\Http\Controllers\Api\V1\TokenController;
use App\Modules\Attendance\Interfaces\Http\Controllers\Api\V1\AttendanceApiController;
use App\Modules\Izin\Interfaces\Http\Controllers\Api\V1\IzinApiController;
use App\Modules\Leave\Interfaces\Http\Controllers\Api\V1\LeaveApiController;
use App\Modules\Overtime\Interfaces\Http\Controllers\Api\V1\OvertimeApiController;
use App\Modules\Payroll\Interfaces\Http\Controllers\Api\V1\PayslipApiController;
use App\Modules\Sppd\Interfaces\Http\Controllers\Api\V1\SppdApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Rute API — token Sanctum (DEC-02), untuk klien non-SPA (mis. mobile).
 * Klien SPA (Vue, kelak) memakai sesi/cookie lewat routes/web.php +
 * statefulApi(), bukan endpoint ini.
 *
 * ESS Mobile (TOR Fase I): endpoint bisnis di bawah ini adalah cermin
 * tipis dari layar ESS web yang sudah ada — memakai Application-layer
 * yang SAMA (SubmitLeaveRequest, SubmitOvertimeRequest, dst), bukan
 * duplikasi logika domain. Unduhan PDF (slip gaji, SPKL) TETAP lewat
 * routes/web.php karena memakai sesi web, bukan token.
 */
Route::prefix('v1')->group(function () {
    // throttle:30,1 murni pagar tambahan (sama seperti /masuk di
    // routes/web.php) — penguncian percobaan gagal sesungguhnya tetap
    // di LoginRequest::ensureIsNotRateLimited() (dipakai TokenController
    // lewat AuthenticateEmployee, jalur SAMA dengan login web).
    Route::post('/auth/login', [TokenController::class, 'store'])->middleware('throttle:30,1');
    Route::post('/auth/logout', [TokenController::class, 'destroy'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', fn (Request $request) => $request->user());

        Route::get('/cuti', [LeaveApiController::class, 'index']);
        Route::post('/cuti', [LeaveApiController::class, 'store']);
        // Batal HANYA saat status='pending' (sebelum tahap 1 diputus) —
        // ditegakkan di CancelLeaveRequest, cermin leave.cancel web.
        Route::post('/cuti/{id}/batal', [LeaveApiController::class, 'cancel']);

        Route::get('/lembur', [OvertimeApiController::class, 'index']);
        Route::post('/lembur', [OvertimeApiController::class, 'store']);
        Route::post('/lembur/{id}/batal', [OvertimeApiController::class, 'cancel']);

        Route::get('/sppd', [SppdApiController::class, 'index']);
        Route::post('/sppd', [SppdApiController::class, 'store']);
        Route::post('/sppd/{id}/batal', [SppdApiController::class, 'cancel']);

        Route::get('/absensi', [AttendanceApiController::class, 'index']);
        Route::post('/absensi', [AttendanceApiController::class, 'store']);

        Route::get('/izin', [IzinApiController::class, 'index']);
        Route::post('/izin', [IzinApiController::class, 'store']);
        Route::post('/izin/{id}/batal', [IzinApiController::class, 'cancel']);

        Route::get('/slip-gaji', [PayslipApiController::class, 'index']);

        Route::get('/notifikasi', [NotificationApiController::class, 'index']);
        Route::post('/notifikasi/{id}/baca', [NotificationApiController::class, 'markAsRead']);

        // Menu Aplikasi Mobile yang boleh tampil — dikendalikan SYSADMIN/
        // Admin HC lewat MobileMenuSettingsController (web).
        Route::get('/menu-mobile', [MobileMenuApiController::class, 'index']);
    });
});
