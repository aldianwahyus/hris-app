<?php

declare(strict_types=1);

use App\Interfaces\Http\Controllers\ApprovalQueueController;
use App\Interfaces\Http\Controllers\AssessmentController;
use App\Interfaces\Http\Controllers\AttendanceDeviceImportController;
use App\Interfaces\Http\Controllers\AttendanceRecapController;
use App\Interfaces\Http\Controllers\AuditLogController;
use App\Interfaces\Http\Controllers\BekalCutiDisbursementController;
use App\Interfaces\Http\Controllers\CompetencyController;
use App\Interfaces\Http\Controllers\DashboardController;
use App\Interfaces\Http\Controllers\DecisionLetterController;
use App\Interfaces\Http\Controllers\EmployeeApprovalQueueController;
use App\Interfaces\Http\Controllers\EmployeeAwardController;
use App\Interfaces\Http\Controllers\EmployeeCertificationController;
use App\Interfaces\Http\Controllers\EmployeeCompetencyController;
use App\Interfaces\Http\Controllers\EmployeeCvController;
use App\Interfaces\Http\Controllers\EmployeeDirectoryController;
use App\Interfaces\Http\Controllers\EmployeeExternalWorkHistoryController;
use App\Interfaces\Http\Controllers\EmployeeFamilyMemberController;
use App\Interfaces\Http\Controllers\EmployeeHealthRecordController;
use App\Interfaces\Http\Controllers\EmployeeImportController;
use App\Interfaces\Http\Controllers\EmployeeInternalWorkHistoryController;
use App\Interfaces\Http\Controllers\EmployeeOrganizationController;
use App\Interfaces\Http\Controllers\EmployeeTrainingController;
use App\Interfaces\Http\Controllers\ForumModerationController;
use App\Interfaces\Http\Controllers\GamificationController;
use App\Interfaces\Http\Controllers\HcDashboardController;
use App\Interfaces\Http\Controllers\IzinApprovalController;
use App\Interfaces\Http\Controllers\JournalAccountController;
use App\Interfaces\Http\Controllers\LearningPathController;
use App\Interfaces\Http\Controllers\LeaveApprovalQueueController;
use App\Interfaces\Http\Controllers\LiveSessionController;
use App\Interfaces\Http\Controllers\LmsAnalyticsController;
use App\Interfaces\Http\Controllers\LmsAssessmentController;
use App\Interfaces\Http\Controllers\LmsCourseBatchController;
use App\Interfaces\Http\Controllers\LmsCourseController;
use App\Interfaces\Http\Controllers\LmsEnrollmentApprovalController;
use App\Interfaces\Http\Controllers\LmsEnrollmentController;
use App\Interfaces\Http\Controllers\LmsForumController;
use App\Interfaces\Http\Controllers\LmsGamificationController;
use App\Interfaces\Http\Controllers\LmsLibraryAdminController;
use App\Interfaces\Http\Controllers\LmsLibraryController;
use App\Interfaces\Http\Controllers\LmsLiveSessionController;
use App\Interfaces\Http\Controllers\NationalHolidayController;
use App\Interfaces\Http\Controllers\OfficeController;
use App\Interfaces\Http\Controllers\OfficeFormasiController;
use App\Interfaces\Http\Controllers\OfficeGeofenceController;
use App\Interfaces\Http\Controllers\OrganizationChartController;
use App\Interfaces\Http\Controllers\OutsideAttendanceApprovalController;
use App\Interfaces\Http\Controllers\OvertimeDisbursementController;
use App\Interfaces\Http\Controllers\OvertimeRecapController;
use App\Interfaces\Http\Controllers\PayrollApprovalController;
use App\Interfaces\Http\Controllers\PositionController;
use App\Interfaces\Http\Controllers\RoleFeatureMapController;
use App\Interfaces\Http\Controllers\SalaryScaleController;
use App\Interfaces\Http\Controllers\ShiftAssignmentController;
use App\Interfaces\Http\Controllers\ShiftPatternController;
use App\Interfaces\Http\Controllers\ShiftSwapApprovalController;
use App\Interfaces\Http\Controllers\SppdApprovalController;
use App\Interfaces\Http\Controllers\SppdDisbursementController;
use App\Interfaces\Http\Controllers\SppdTariffAdminController;
use App\Interfaces\Http\Controllers\SuccessionPlanController;
use App\Interfaces\Http\Controllers\SystemAdminEmployeeController;
use App\Interfaces\Http\Controllers\SystemAdminUserController;
use App\Interfaces\Http\Controllers\SystemParameterController;
use App\Interfaces\Http\Controllers\TalentProfileController;
use App\Interfaces\Http\Controllers\TrainingEvaluationController;
use App\Modules\Access\Interfaces\Http\Controllers\LoginController;
use App\Modules\Access\Interfaces\Http\Controllers\LogoutController;
use App\Modules\Attendance\Interfaces\Http\Controllers\AttendanceController;
use App\Modules\Izin\Interfaces\Http\Controllers\IzinRequestController;
use App\Modules\Leave\Interfaces\Http\Controllers\LeaveRequestController;
use App\Modules\Overtime\Interfaces\Http\Controllers\OvertimeRequestController;
use App\Modules\Payroll\Interfaces\Http\Controllers\PayrollDeductionController;
use App\Modules\Payroll\Interfaces\Http\Controllers\PayslipController;
use App\Modules\Shift\Interfaces\Http\Controllers\ShiftSwapRequestController;
use App\Modules\Sppd\Interfaces\Http\Controllers\SppdRequestController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Rute antarmuka web.
 *
 * Sanctum (sesi/cookie) sebagai identity provider tunggal (DEC-02) —
 * seluruh layar operasional kini berada di belakang 'auth', dengan
 * lingkup peran per rute mengikuti ARCH-001 §6.2/§6.3.
 */
// throttle:30,1 di sini murni pagar tambahan (mis. scraping halaman
// masuk) — penguncian percobaan gagal sesungguhnya ada di
// LoginRequest::ensureIsNotRateLimited(), bukan di sini.
Route::middleware(['guest', 'throttle:30,1'])->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store']);

    // Gambar captcha di-generate ulang lewat rute ini (tombol refresh di
    // login.blade.php) — bukan reload halaman penuh, supaya field NRP/kata
    // sandi yang sudah diisi pengguna tidak hilang saat captcha diganti.
    Route::get('/captcha-refresh', fn () => response()->json(['captcha' => captcha_src('flat')]))
        ->name('captcha.refresh');
});

Route::post('/keluar', LogoutController::class)->middleware('auth')->name('logout');

// throttle:60,1 (1 permintaan/detik rata-rata) — jauh di atas kecepatan
// klik manusia wajar, tapi menumpulkan penyalahgunaan otomatis atas
// sesi yang bocor/dicuri. Lapisan tambahan, bukan pengganti autentikasi.
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    // Admin Sistem tidak punya beranda pegawai yang berarti (bukan
    // pegawai SDM sungguhan, lihat Role::SystemAdmin) — diarahkan
    // langsung ke manajemen pengguna.
    Route::get('/', fn () => redirect()->route(
        Auth::user()->hasRole('system_admin') ? 'sysadmin.users.index' : 'ess.dashboard'
    ));

    // Lingkup SELF — pemilik selalu dapat melihat datanya sendiri,
    // terlepas dari peran apa pun yang dipegangnya.
    Route::get('/beranda', DashboardController::class)->name('ess.dashboard');

    // "CV Saya" — data organisasi HANYA-BACA, data pribadi
    // (SelfEditableEmployeeField) diubah LANGSUNG tanpa persetujuan
    // (beda dari data organisasi lewat maker-checker hr_admin/SYSADMIN).
    Route::get('/cv-saya', [EmployeeCvController::class, 'show'])->name('ess.cv');
    Route::post('/cv-saya', [EmployeeCvController::class, 'update'])->name('ess.cv.update');

    // 4 riwayat self-report (Pelatihan/Sertifikasi/Organisasi/
    // Penghargaan) — pegawai kelola SENDIRI, tulis langsung tanpa
    // persetujuan, pola sama ess.cv.update di atas.
    Route::prefix('cv-saya')->name('ess.cv.')->group(function () {
        Route::get('/unduh', [EmployeeCvController::class, 'pdf'])->name('pdf');
        Route::get('/sk/{id}/unduh', [EmployeeCvController::class, 'downloadSk'])->name('sk.download');

        Route::post('/pelatihan', [EmployeeTrainingController::class, 'store'])->name('trainings.store');
        Route::delete('/pelatihan/{id}', [EmployeeTrainingController::class, 'destroy'])->name('trainings.destroy');

        Route::post('/sertifikasi', [EmployeeCertificationController::class, 'store'])->name('certifications.store');
        Route::delete('/sertifikasi/{id}', [EmployeeCertificationController::class, 'destroy'])->name('certifications.destroy');

        Route::post('/organisasi', [EmployeeOrganizationController::class, 'store'])->name('organizations.store');
        Route::delete('/organisasi/{id}', [EmployeeOrganizationController::class, 'destroy'])->name('organizations.destroy');

        Route::post('/penghargaan', [EmployeeAwardController::class, 'store'])->name('awards.store');
        Route::delete('/penghargaan/{id}', [EmployeeAwardController::class, 'destroy'])->name('awards.destroy');
    });

    // Tahap 2 — Pengajuan dari Layar. Selalu atas nama pegawai yang
    // sedang masuk (ownership); tidak ada parameter employee_id di sini.
    Route::prefix('cuti')->name('leave.')->group(function () {
        Route::get('/ajukan', [LeaveRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [LeaveRequestController::class, 'store'])->name('store');
    });

    Route::prefix('lembur')->name('overtime.')->group(function () {
        Route::get('/ajukan', [OvertimeRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [OvertimeRequestController::class, 'store'])->name('store');
        // Kewenangan diperiksa di dalam controller (pemohon/penyetuju/
        // hr_admin kantor sendiri/hr_approver/auditor bank-wide) —
        // bukan lewat middleware role tunggal, karena SPKL berpindah
        // relevansi tergantung siapa terlibat pada satu pengajuan.
        Route::get('/spkl/{id}', [OvertimeRequestController::class, 'downloadSpkl'])->name('spkl');
    });

    // Tahap 4 — Absensi GPS. Selalu atas nama pegawai yang sedang
    // masuk (ownership); tidak ada parameter employee_id di sini.
    Route::prefix('absensi')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('create');
        Route::post('/', [AttendanceController::class, 'store'])->name('store');

        // Absen luar kantor — pegawai lapangan mengajukan, disetujui
        // Pimpinan Kantor SATU TAHAP (lihat OutsideAttendanceApprovalController),
        // BUKAN lewat GeofencePolicy.
        Route::get('/luar-kantor/ajukan', [AttendanceController::class, 'createOutside'])->name('outside.create');
        Route::post('/luar-kantor/ajukan', [AttendanceController::class, 'storeOutside'])->name('outside.store');
    });

    // Tahap 5 — Payroll & Pajak (sebagian, BPP/137/03/64/2026).
    // Lingkup SELF — hanya slip dari run yang sudah disetujui.
    Route::get('/slip-gaji', [PayslipController::class, 'index'])->name('payslip.index');
    Route::get('/slip-gaji/{id}/unduh', [PayslipController::class, 'download'])->name('payslip.download');

    // Tahap 6 — SPPD (BPP/442/03/64/2026). Selalu atas nama pegawai yang
    // sedang masuk (ownership); tidak ada parameter employee_id di sini.
    Route::prefix('sppd')->name('sppd.')->group(function () {
        Route::get('/ajukan', [SppdRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [SppdRequestController::class, 'store'])->name('store');
    });

    // Tukar Shift — selalu atas nama pegawai yang login (pemohon).
    // Rekan yang dituju TIDAK dimintai konfirmasi terpisah, langsung ke
    // antrean Atasan Langsung (1 tahap, lihat ShiftSwapApprovalController).
    Route::prefix('tukar-shift')->name('shift.')->group(function () {
        Route::get('/ajukan', [ShiftSwapRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [ShiftSwapRequestController::class, 'store'])->name('store');
    });

    // Izin Tidak Masuk Bekerja — TERPISAH dari Cuti (tidak menyentuh
    // leave_balances), langsung ke antrean Atasan Langsung (1 tahap,
    // lihat IzinApprovalController), pola SAMA PERSIS Tukar Shift.
    Route::prefix('izin')->name('izin.')->group(function () {
        Route::get('/ajukan', [IzinRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [IzinRequestController::class, 'store'])->name('store');
        Route::get('/{id}/lampiran', [IzinRequestController::class, 'downloadAttachment'])->name('attachment');
    });

    // Pelatihan (LMS) — pegawai jelajah jadwal terbuka & mendaftar
    // sendiri, langsung ke antrean Atasan Langsung (1 tahap, lihat
    // LmsEnrollmentApprovalController) — pola sama Tukar Shift.
    Route::prefix('pelatihan')->name('lms.')->group(function () {
        Route::get('/', [LmsEnrollmentController::class, 'index'])->name('index');
        Route::post('/daftar', [LmsEnrollmentController::class, 'store'])->name('store');
        Route::get('/saya', [LmsEnrollmentController::class, 'mine'])->name('mine');
        Route::get('/sertifikat/{id}', [LmsEnrollmentController::class, 'certificate'])->name('certificate');

        // Digital Library (BRD §5.7) — semua pegawai boleh menjelajah,
        // pola sama rute di atas (tanpa middleware permission).
        Route::get('/perpustakaan', [LmsLibraryController::class, 'index'])->name('library.index');
        Route::get('/perpustakaan/{id}/buka', [LmsLibraryController::class, 'open'])->name('library.open');

        // Learning Path — "IDP" pegawai (BRD §5.2).
        Route::get('/rencana-pengembangan', [LmsEnrollmentController::class, 'developmentPlan'])->name('development-plan');

        // Gamifikasi (BRD §5.8) — poin otomatis dari kelulusan kursus/
        // asesmen, badge diberikan HC, challenge diikuti sendiri.
        Route::get('/papan-peringkat', [LmsGamificationController::class, 'leaderboard'])->name('leaderboard');
        Route::get('/lencana-saya', [LmsGamificationController::class, 'myBadges'])->name('my-badges');
        Route::post('/challenge/{challengeId}/ikuti', [LmsGamificationController::class, 'joinChallenge'])->name('challenge.join');

        // Evaluasi Pelatihan Level 1 — Kepuasan peserta (BRD §5.5).
        Route::get('/pendaftaran/{enrollmentId}/evaluasi', [LmsEnrollmentController::class, 'evaluate'])->name('evaluation.show');
        Route::post('/pendaftaran/{enrollmentId}/evaluasi', [LmsEnrollmentController::class, 'storeEvaluation'])->name('evaluation.store');
    });

    // Social & Collaborative Learning (BRD §5.9) — ESS, TANPA
    // middleware permission (moderasi lewat ForumModerationController/HC).
    Route::prefix('pelatihan/forum')->name('lms.forum.')->group(function () {
        Route::get('/', [LmsForumController::class, 'index'])->name('index');
        Route::get('/buat', [LmsForumController::class, 'create'])->name('create');
        Route::post('/', [LmsForumController::class, 'store'])->name('store');
        Route::get('/{id}', [LmsForumController::class, 'show'])->name('show');
        Route::post('/{threadId}/balas', [LmsForumController::class, 'storeReply'])->name('reply');
    });

    // Live Learning & Mentoring (BRD §5.10) — ESS, TANPA middleware permission.
    Route::prefix('pelatihan/sesi-live')->name('lms.live-sessions.')->group(function () {
        Route::get('/', [LmsLiveSessionController::class, 'index'])->name('index');
        Route::post('/{sessionId}/daftar', [LmsLiveSessionController::class, 'register'])->name('register');
    });

    // Assessment Center (BRD §5.4) — ESS, TANPA middleware permission.
    Route::prefix('pelatihan/asesmen')->name('lms.assessment.')->group(function () {
        Route::get('/', [LmsAssessmentController::class, 'index'])->name('index');
        Route::post('/{assessmentId}/mulai', [LmsAssessmentController::class, 'start'])->name('start');
        Route::get('/kerjakan/{attemptId}', [LmsAssessmentController::class, 'take'])->name('take');
        Route::post('/kerjakan/{attemptId}', [LmsAssessmentController::class, 'submit'])->name('submit');
        Route::get('/hasil/{attemptId}', [LmsAssessmentController::class, 'result'])->name('result');
    });

    // Lembur 2 TAHAP (koreksi DEC-92 versi awal — lihat
    // ApprovalQueueController): Atasan Langsung dulu, baru Pimpinan
    // Kantor. direktur_bidang/direktur_pembina TIDAK LAGI dipakai di
    // sini. Middleware role di sini hanya gerbang KASAR ("punya salah
    // satu peran ini") — pemilahan tahap yang PRESISI (mana yang boleh
    // memutus status 'pending' vs 'pending_pimpinan') ditegakkan di
    // dalam controller, bukan di middleware.
    Route::prefix('persetujuan')->name('admin.')->group(function () {
        Route::get('/lembur', [ApprovalQueueController::class, 'index'])
            ->middleware('permission:overtime-approval.view')
            ->name('approval-queue');

        // Auditor sengaja tidak disertakan — perannya hanya-baca (§6.3).
        Route::post('/lembur/{id}/setujui', [ApprovalQueueController::class, 'approve'])
            ->middleware('permission:overtime-approval.decide')
            ->name('approve');
        Route::post('/lembur/{id}/tolak', [ApprovalQueueController::class, 'reject'])
            ->middleware('permission:overtime-approval.decide')
            ->name('reject');

        // Pembayaran Lembur MASSAL (Admin HC) — HANYA kantor pusat,
        // dipilih per divisi. Cabang/KCP dibayar Admin Cabang lewat
        // rute terpisah di bawah (lingkup OFFICE, prefix pegawai/),
        // lihat OvertimeDisbursementController.
        Route::get('/lembur-pembayaran', [OvertimeDisbursementController::class, 'indexForHc'])
            ->middleware('permission:overtime-disbursement.hc')
            ->name('overtime-disbursement-queue');
        Route::post('/lembur-pembayaran/bayar', [OvertimeDisbursementController::class, 'processBatchForHc'])
            ->middleware('permission:overtime-disbursement.hc')
            ->name('overtime-disburse');

        // Detail batch + cetak — diakses HC MAUPUN Admin Cabang (batch
        // masing-masing), pembatasan halus (kantor sendiri vs bank-wide)
        // ditegakkan di controller (guardBatchAccess()), bukan di sini.
        Route::prefix('lembur-pembayaran/batch')->name('overtime-payment-batch.')->middleware('permission:overtime-disbursement.hc|overtime-disbursement.branch')->group(function () {
            Route::get('/{id}', [OvertimeDisbursementController::class, 'showBatch'])->name('show');
            Route::get('/{id}/cetak/memo', [OvertimeDisbursementController::class, 'printMemo'])->name('print-memo');
            Route::get('/{id}/cetak/nota-debet', [OvertimeDisbursementController::class, 'printNotaDebet'])->name('print-nota-debet');
            Route::get('/{id}/cetak/jurnal-slip', [OvertimeDisbursementController::class, 'printJurnalSlip'])->name('print-jurnal-slip');
        });

        // Cuti SEKARANG 2 TAHAP (pola sama Lembur, lihat
        // LeaveApprovalQueueController) — hr_approver DIHAPUS dari
        // jalur keputusan Cuti.
        Route::get('/cuti', [LeaveApprovalQueueController::class, 'index'])
            ->middleware('permission:leave-approval.view')
            ->name('leave-approval-queue');

        Route::post('/cuti/{id}/setujui', [LeaveApprovalQueueController::class, 'approve'])
            ->middleware('permission:leave-approval.decide')
            ->name('leave-approve');
        Route::post('/cuti/{id}/tolak', [LeaveApprovalQueueController::class, 'reject'])
            ->middleware('permission:leave-approval.decide')
            ->name('leave-reject');

        // Pencairan Bekal Cuti MASSAL (Admin HC, BANK_WIDE, dipilih per
        // divisi) — baris pay_bekal_cuti_disbursements dipicu otomatis
        // saat Cuti Tahunan disetujui tahap 2, lihat
        // LeaveApprovalQueueController::triggerBekalCutiIfFirstThisYear();
        // pembayarannya sendiri sekarang batch, pola sama
        // OvertimeDisbursementController — lihat ProcessBekalCutiPaymentBatch.
        Route::get('/bekal-cuti', [BekalCutiDisbursementController::class, 'indexForHc'])
            ->middleware('permission:bekal-cuti-disbursement.hc')
            ->name('bekal-cuti-queue');
        Route::post('/bekal-cuti/bayar', [BekalCutiDisbursementController::class, 'processBatchForHc'])
            ->middleware('permission:bekal-cuti-disbursement.hc')
            ->name('bekal-cuti-disburse');

        Route::prefix('bekal-cuti/batch')->name('bekal-cuti-payment-batch.')->middleware('permission:bekal-cuti-disbursement.hc|bekal-cuti-disbursement.branch')->group(function () {
            Route::get('/{id}', [BekalCutiDisbursementController::class, 'showBatch'])->name('show');
            Route::get('/{id}/cetak/memo', [BekalCutiDisbursementController::class, 'printMemo'])->name('print-memo');
            Route::get('/{id}/cetak/nota-debet', [BekalCutiDisbursementController::class, 'printNotaDebet'])->name('print-nota-debet');
        });

        // Payroll — checker BANK_WIDE (Pejabat SDM), bukan Direktur
        // Bidang/Pembina (DEC-92 khusus Lembur, tidak berlaku di sini).
        Route::get('/payroll', [PayrollApprovalController::class, 'index'])
            ->middleware('permission:payroll-approval.manage')
            ->name('payroll-approval-queue');
        // Detail baca-saja "gaji final setelah potongan" per run (draft
        // ATAU approved) — lihat PayrollApprovalController::show().
        Route::get('/payroll/{id}', [PayrollApprovalController::class, 'show'])
            ->middleware('permission:payroll-approval.manage')
            ->name('payroll-run-detail');
        Route::post('/payroll/{id}/setujui', [PayrollApprovalController::class, 'approve'])
            ->middleware('permission:payroll-approval.manage')
            ->name('payroll-approve');
        Route::post('/payroll/{id}/tolak', [PayrollApprovalController::class, 'reject'])
            ->middleware('permission:payroll-approval.manage')
            ->name('payroll-reject');
        // Melengkapi hr.payroll.store (hr_admin, per-kantor) — BUKAN
        // menggantikannya, lihat RunPayrollDraftForAllOffices.
        // throttle:5,1 — lebih ketat dari batas 60/menit pada grup
        // 'auth' di luar sini: ini menulis draf payroll SELURUH bank
        // sekaligus per permintaan, jauh lebih berat daripada memuat
        // satu halaman.
        Route::post('/payroll/generate-massal', [PayrollApprovalController::class, 'generateBulk'])
            ->middleware(['permission:payroll-approval.manage', 'throttle:5,1'])
            ->name('payroll-generate-bulk');
        // Menutup input potongan SELURUH kantor untuk satu periode
        // sekaligus (Kantor Pusat + KC + KCP) — lihat
        // DecidePayrollRun::approveAllForPeriod(). BEDA dari
        // payroll-approve (per-run): di sini larangan self-approval
        // SENGAJA tidak berlaku, keputusan eksplisit pengguna.
        Route::post('/payroll/tutup-periode', [PayrollApprovalController::class, 'closePeriod'])
            ->middleware(['permission:payroll-approval.manage', 'throttle:5,1'])
            ->name('payroll-close-period');
        // Satu-satunya cara mengembalikan akses admin cabang ke potongan
        // gaji (PayrollDeductionController) setelah run di-approve —
        // lihat DecidePayrollRun::reopen().
        Route::post('/payroll/{id}/buka-kembali', [PayrollApprovalController::class, 'reopen'])
            ->middleware('permission:payroll-approval.manage')
            ->name('payroll-reopen');

        // SPPD SEKARANG 2 tahap seragam untuk SEMUA kategori (lihat
        // SppdApprovalController) — Atasan Langsung dulu, baru Pimpinan
        // Kantor. hr_approver DIHAPUS dari jalur keputusan.
        Route::get('/sppd', [SppdApprovalController::class, 'index'])
            ->middleware('permission:sppd-approval.view')
            ->name('sppd-approval-queue');
        Route::post('/sppd/{id}/setujui', [SppdApprovalController::class, 'approve'])
            ->middleware('permission:sppd-approval.decide')
            ->name('sppd-approve');
        Route::post('/sppd/{id}/tolak', [SppdApprovalController::class, 'reject'])
            ->middleware('permission:sppd-approval.decide')
            ->name('sppd-reject');

        // Tukar Shift — 1 TAHAP, Atasan Langsung SAJA (tidak berdampak
        // finansial/saldo cuti, tidak butuh Pimpinan Kantor — lihat
        // ShiftSwapApprovalController).
        Route::get('/tukar-shift', [ShiftSwapApprovalController::class, 'index'])
            ->middleware('permission:shift-swap-approval.view')
            ->name('shift-swap-queue');
        Route::post('/tukar-shift/{id}/setujui', [ShiftSwapApprovalController::class, 'approve'])
            ->middleware('permission:shift-swap-approval.decide')
            ->name('shift-swap-approve');
        Route::post('/tukar-shift/{id}/tolak', [ShiftSwapApprovalController::class, 'reject'])
            ->middleware('permission:shift-swap-approval.decide')
            ->name('shift-swap-reject');

        // Izin Tidak Masuk Bekerja — 1 TAHAP, Atasan Langsung SAJA, pola
        // SAMA PERSIS Tukar Shift (lihat IzinApprovalController).
        Route::get('/izin', [IzinApprovalController::class, 'index'])
            ->middleware('permission:izin-approval.view')
            ->name('izin-queue');
        Route::post('/izin/{id}/setujui', [IzinApprovalController::class, 'approve'])
            ->middleware('permission:izin-approval.decide')
            ->name('izin-approve');
        Route::post('/izin/{id}/tolak', [IzinApprovalController::class, 'reject'])
            ->middleware('permission:izin-approval.decide')
            ->name('izin-reject');
        Route::get('/izin/{id}/lampiran', [IzinApprovalController::class, 'downloadAttachment'])
            ->middleware('permission:izin-approval.view')
            ->name('izin-attachment');

        // Pelatihan — 1 TAHAP, Atasan Langsung SAJA (pola sama Tukar
        // Shift, tidak berdampak finansial langsung). Auditor hanya-baca.
        Route::get('/pelatihan', [LmsEnrollmentApprovalController::class, 'index'])
            ->middleware('permission:lms-enrollment-approval.view')
            ->name('lms-enrollment-queue');
        Route::post('/pelatihan/{id}/setujui', [LmsEnrollmentApprovalController::class, 'approve'])
            ->middleware('permission:lms-enrollment-approval.decide')
            ->name('lms-enrollment-approve');
        Route::post('/pelatihan/{id}/tolak', [LmsEnrollmentApprovalController::class, 'reject'])
            ->middleware('permission:lms-enrollment-approval.decide')
            ->name('lms-enrollment-reject');

        // Absen Luar Kantor — 1 TAHAP, Pimpinan Kantor SAJA, lingkup kantor
        // sendiri (OFFICE EXACT — lihat OutsideAttendanceApprovalController).
        Route::get('/absensi-luar-kantor', [OutsideAttendanceApprovalController::class, 'index'])
            ->middleware('permission:outside-attendance-approval.view')
            ->name('outside-attendance-queue');
        Route::post('/absensi-luar-kantor/{id}/setujui', [OutsideAttendanceApprovalController::class, 'approve'])
            ->middleware('permission:outside-attendance-approval.decide')
            ->name('outside-attendance-approve');
        Route::post('/absensi-luar-kantor/{id}/tolak', [OutsideAttendanceApprovalController::class, 'reject'])
            ->middleware('permission:outside-attendance-approval.decide')
            ->name('outside-attendance-reject');

        // Pencairan — tahap lanjutan setelah persetujuan, SEKARANG oleh
        // Admin HC (hr_approver, BANK_WIDE) menggantikan Treasury (peran
        // itu berhenti dipakai di sini, tidak dihapus dari enum — lihat
        // hr.sppd-disbursement.* di bawah untuk Admin Cabang, kantor
        // sendiri). Larangan mencairkan pengajuan yang disetujui sendiri
        // ditegakkan di DisburseSppdRequest (§6.3).
        Route::get('/sppd-pencairan', [SppdDisbursementController::class, 'indexForHc'])
            ->middleware('permission:sppd-disbursement.hc.view')
            ->name('sppd-disbursement-queue');
        Route::post('/sppd-pencairan/{id}/cairkan', [SppdDisbursementController::class, 'disburseForHc'])
            ->middleware('permission:sppd-disbursement.hc.decide')
            ->name('sppd-disburse');

        // Data Pegawai — checker BANK_WIDE (hr_approver). Larangan
        // menyetujui pengajuan sendiri ditegakkan di
        // EmployeeApprovalQueueController (§6.3).
        Route::get('/pegawai', [EmployeeApprovalQueueController::class, 'index'])
            ->middleware('permission:employee-approval.manage')
            ->name('employee-approval-queue');
        Route::post('/pegawai/{id}/setujui', [EmployeeApprovalQueueController::class, 'approve'])
            ->middleware('permission:employee-approval.manage')
            ->name('employee-approve');
        Route::post('/pegawai/{id}/tolak', [EmployeeApprovalQueueController::class, 'reject'])
            ->middleware('permission:employee-approval.manage')
            ->name('employee-reject');

        // Usulan PEGAWAI BARU (SYSADMIN, maker) — tabel terpisah,
        // lihat SubmitNewEmployeeRequest/DecideNewEmployeeRequest.
        Route::post('/pegawai-baru/{id}/setujui', [EmployeeApprovalQueueController::class, 'approveNewEmployee'])
            ->middleware('permission:employee-approval.manage')
            ->name('new-employee-approve');
        Route::post('/pegawai-baru/{id}/tolak', [EmployeeApprovalQueueController::class, 'rejectNewEmployee'])
            ->middleware('permission:employee-approval.manage')
            ->name('new-employee-reject');
    });

    // Lingkup OFFICE — Admin SDM (maker). edit()/update() mengajukan
    // perubahan, TIDAK menulis emp_employees langsung — lihat
    // EmployeeApprovalQueueController untuk sisi checker (hr_approver).
    Route::get('/pegawai', [EmployeeDirectoryController::class, 'index'])
        ->middleware('permission:employee-directory.manage')
        ->name('hr.employees');
    Route::get('/pegawai/{id}/ubah', [EmployeeDirectoryController::class, 'edit'])
        ->middleware('permission:employee-directory.manage')
        ->name('hr.employees.edit');
    Route::post('/pegawai/{id}/ubah', [EmployeeDirectoryController::class, 'update'])
        ->middleware('permission:employee-directory.manage')
        ->name('hr.employees.update');

    // Data riwayat pegawai (Data Pasangan & Anak, Riwayat Kerja
    // Internal/Eksternal, Sanksi, Riwayat Kesehatan) — HR-only, tulis
    // LANGSUNG (BUKAN maker-checker). Satu set rute dipakai BERSAMA
    // oleh halaman ubah pegawai hr_admin MAUPUN SYSADMIN/hr_approver —
    // lingkup ditegakkan di ResolveEmployeeForHrAction, BUKAN dengan
    // menduplikasi rute per peran.
    Route::prefix('pegawai/{employeeId}')
        ->middleware('permission:employee-records.manage')
        ->name('employee-records.')
        ->group(function () {
            Route::post('/keluarga', [EmployeeFamilyMemberController::class, 'store'])->name('family.store');
            Route::delete('/keluarga/{id}', [EmployeeFamilyMemberController::class, 'destroy'])->name('family.destroy');

            Route::post('/riwayat-internal', [EmployeeInternalWorkHistoryController::class, 'store'])->name('internal-history.store');
            Route::delete('/riwayat-internal/{id}', [EmployeeInternalWorkHistoryController::class, 'destroy'])->name('internal-history.destroy');

            Route::post('/riwayat-eksternal', [EmployeeExternalWorkHistoryController::class, 'store'])->name('external-history.store');
            Route::delete('/riwayat-eksternal/{id}', [EmployeeExternalWorkHistoryController::class, 'destroy'])->name('external-history.destroy');

            Route::post('/riwayat-kesehatan', [EmployeeHealthRecordController::class, 'store'])->name('health-record.store');
            Route::delete('/riwayat-kesehatan/{id}', [EmployeeHealthRecordController::class, 'destroy'])->name('health-record.destroy');
        });

    // Surat Keputusan (SK) — modul TERSENDIRI (BUKAN bagian dari Data
    // Pegawai/employee-records di atas), untuk SYSADMIN/hr_admin. Bisa
    // untuk satu pegawai atau banyak sekaligus (massal, lihat
    // DecisionLetterController::store()). Lingkup ditegakkan di
    // ResolveEmployeeForHrAction (dipanggil per pegawai di controller),
    // BUKAN di middleware ini — middleware hanya gerbang KASAR peran.
    Route::prefix('sk')
        ->middleware('permission:decision-letter.manage')
        ->name('sk.')
        ->group(function () {
            Route::get('/', [DecisionLetterController::class, 'index'])->name('index');
            Route::get('/buat', [DecisionLetterController::class, 'create'])->name('create');
            Route::post('/', [DecisionLetterController::class, 'store'])->name('store');
            Route::delete('/{id}', [DecisionLetterController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/unduh', [DecisionLetterController::class, 'download'])->name('download');

            // SK Perubahan Gaji — layar KHUSUS (bukan form massal di atas),
            // gaji saat ini berbeda-beda per pegawai jadi tidak cocok
            // dengan pola "satu target untuk semua tercentang" seperti
            // Mutasi/Promosi. Lihat DecisionLetterController::createSalaryChange().
            Route::prefix('perubahan-gaji')->name('salary-change.')->group(function () {
                Route::get('/buat', [DecisionLetterController::class, 'createSalaryChange'])->name('create');
                Route::post('/', [DecisionLetterController::class, 'storeSalaryChange'])->name('store');
                Route::get('/template', [DecisionLetterController::class, 'salaryChangeTemplate'])->name('template');
                Route::post('/impor', [DecisionLetterController::class, 'importSalaryChange'])->name('import');
            });
        });

    // Katalog Pelatihan (LMS) — HC (hr_admin/hr_approver/system_admin),
    // BANK_WIDE (bukan office-scoped seperti Data Pegawai — katalog
    // pelatihan berlaku bank-wide, bukan per kantor).
    Route::prefix('admin/pelatihan')
        ->middleware('permission:lms-catalog.manage')
        ->name('lms.admin.')
        ->group(function () {
            Route::get('/', [LmsCourseController::class, 'index'])->name('courses.index');
            Route::post('/', [LmsCourseController::class, 'store'])->name('courses.store');
            Route::post('/{id}/ubah', [LmsCourseController::class, 'update'])->name('courses.update');
            Route::delete('/{id}', [LmsCourseController::class, 'destroy'])->name('courses.destroy');

            Route::post('/{courseId}/jadwal', [LmsCourseBatchController::class, 'store'])->name('batches.store');
            Route::post('/jadwal/{id}/ubah', [LmsCourseBatchController::class, 'update'])->name('batches.update');
            Route::delete('/jadwal/{id}', [LmsCourseBatchController::class, 'destroy'])->name('batches.destroy');
            Route::get('/jadwal/{id}/peserta', [LmsCourseBatchController::class, 'roster'])->name('batches.roster');
            Route::post('/pendaftaran/{enrollmentId}/kelulusan', [LmsCourseBatchController::class, 'recordCompletion'])
                ->name('batches.record-completion');

            // Sesi (hari pertemuan) + absensi per sesi (BRD §5.3).
            Route::get('/jadwal/{batchId}/sesi', [LmsCourseBatchController::class, 'sessions'])->name('batches.sessions');
            Route::post('/jadwal/{batchId}/sesi', [LmsCourseBatchController::class, 'storeSession'])->name('batches.sessions.store');
            Route::get('/sesi/{sessionId}/absensi', [LmsCourseBatchController::class, 'attendance'])->name('sessions.attendance');
            Route::post('/sesi/{sessionId}/absensi', [LmsCourseBatchController::class, 'storeAttendance'])->name('sessions.attendance.store');

            // Digital Library — pengelolaan HC (BRD §5.7).
            Route::get('/perpustakaan', [LmsLibraryAdminController::class, 'index'])->name('library.index');
            Route::post('/perpustakaan', [LmsLibraryAdminController::class, 'store'])->name('library.store');
            Route::post('/perpustakaan/{id}', [LmsLibraryAdminController::class, 'update'])->name('library.update');

            // Competency-Based Learning (BRD §5.1) — Phase 2.
            Route::get('/kompetensi', [CompetencyController::class, 'index'])->name('competencies.index');
            Route::post('/kompetensi', [CompetencyController::class, 'store'])->name('competencies.store');
            Route::post('/kompetensi/{id}', [CompetencyController::class, 'update'])->name('competencies.update');
            Route::get('/kompetensi/jabatan/{positionId}', [CompetencyController::class, 'mapPosition'])->name('competencies.map-position');
            Route::post('/kompetensi/jabatan/{positionId}', [CompetencyController::class, 'storePositionMapping'])->name('competencies.map-position.store');
            Route::get('/kompetensi/kursus/{courseId}', [CompetencyController::class, 'mapCourse'])->name('competencies.map-course');
            Route::post('/kompetensi/kursus/{courseId}', [CompetencyController::class, 'storeCourseMapping'])->name('competencies.map-course.store');

            Route::get('/kompetensi-pegawai/{employeeId}', [EmployeeCompetencyController::class, 'show'])->name('employee-competency.show');
            Route::post('/kompetensi-pegawai/{employeeId}', [EmployeeCompetencyController::class, 'update'])->name('employee-competency.update');

            // Learning Path & Career Development (BRD §5.2) — Phase 2.
            Route::get('/learning-path', [LearningPathController::class, 'index'])->name('learning-paths.index');
            Route::post('/learning-path', [LearningPathController::class, 'store'])->name('learning-paths.store');
            Route::get('/learning-path/{id}', [LearningPathController::class, 'show'])->name('learning-paths.show');
            Route::post('/learning-path/{pathId}/kursus', [LearningPathController::class, 'storeCourse'])->name('learning-paths.courses.store');
            Route::delete('/learning-path/{pathId}/kursus/{id}', [LearningPathController::class, 'destroyCourse'])->name('learning-paths.courses.destroy');

            // Assessment Center (BRD §5.4) — Phase 2.
            Route::get('/asesmen', [AssessmentController::class, 'index'])->name('assessments.index');
            Route::post('/asesmen', [AssessmentController::class, 'store'])->name('assessments.store');
            Route::post('/asesmen/{id}', [AssessmentController::class, 'update'])->name('assessments.update');
            Route::get('/asesmen/{assessmentId}/soal', [AssessmentController::class, 'questions'])->name('assessments.questions');
            Route::post('/asesmen/{assessmentId}/soal', [AssessmentController::class, 'storeQuestion'])->name('assessments.questions.store');
            Route::delete('/asesmen/{assessmentId}/soal/{id}', [AssessmentController::class, 'destroyQuestion'])->name('assessments.questions.destroy');
            Route::get('/asesmen/{assessmentId}/hasil', [AssessmentController::class, 'attempts'])->name('assessments.attempts');
            Route::get('/asesmen/percobaan/{attemptId}/nilai', [AssessmentController::class, 'grade'])->name('assessments.grade');
            Route::post('/asesmen/percobaan/{attemptId}/nilai', [AssessmentController::class, 'storeGrade'])->name('assessments.grade.store');

            // Talent Management (BRD §5.6) — Phase 2.
            Route::get('/talenta', [TalentProfileController::class, 'index'])->name('talent.index');
            Route::get('/talenta/{employeeId}', [TalentProfileController::class, 'show'])->name('talent.show');
            Route::post('/talenta/{employeeId}', [TalentProfileController::class, 'update'])->name('talent.update');

            Route::get('/suksesi', [SuccessionPlanController::class, 'index'])->name('succession.index');
            Route::post('/suksesi', [SuccessionPlanController::class, 'store'])->name('succession.store');
            Route::delete('/suksesi/{id}', [SuccessionPlanController::class, 'destroy'])->name('succession.destroy');

            // Gamifikasi (BRD §5.8) — Phase 3.
            Route::get('/lencana', [GamificationController::class, 'badgesIndex'])->name('badges.index');
            Route::post('/lencana', [GamificationController::class, 'storeBadge'])->name('badges.store');
            Route::post('/lencana/beri', [GamificationController::class, 'awardBadge'])->name('badges.award');

            Route::get('/challenge', [GamificationController::class, 'challengesIndex'])->name('challenges.index');
            Route::post('/challenge', [GamificationController::class, 'storeChallenge'])->name('challenges.store');
            Route::get('/challenge/{challengeId}/peserta', [GamificationController::class, 'challengeParticipants'])->name('challenges.participants');
            Route::post('/challenge/{challengeId}/peserta/{employeeId}/selesai', [GamificationController::class, 'markChallengeCompleted'])->name('challenges.complete');

            Route::get('/papan-peringkat', [GamificationController::class, 'leaderboard'])->name('leaderboard.index');

            // Reporting + Advanced Analytics (BRD §5.11 + §5.12) — Phase 3.
            Route::get('/analitik', [LmsAnalyticsController::class, 'dashboard'])->name('analytics.dashboard');
            Route::get('/analitik/ekspor', [LmsAnalyticsController::class, 'exportDashboard'])->name('analytics.dashboard.export');
            Route::get('/analitik/pelatihan', [LmsAnalyticsController::class, 'trainingReport'])->name('analytics.training-report');
            Route::get('/analitik/pelatihan/ekspor', [LmsAnalyticsController::class, 'exportTrainingReport'])->name('analytics.training-report.export');
            Route::get('/analitik/evaluasi', [LmsAnalyticsController::class, 'evaluationReport'])->name('analytics.evaluation-report');
            Route::get('/analitik/evaluasi/ekspor', [LmsAnalyticsController::class, 'exportEvaluationReport'])->name('analytics.evaluation-report.export');
            Route::get('/analitik/kompetensi', [LmsAnalyticsController::class, 'competencyReport'])->name('analytics.competency-report');
            Route::get('/analitik/kompetensi/ekspor', [LmsAnalyticsController::class, 'exportCompetencyReport'])->name('analytics.competency-report.export');
            Route::get('/analitik/talenta', [LmsAnalyticsController::class, 'talentReport'])->name('analytics.talent-report');
            Route::get('/analitik/talenta/ekspor', [LmsAnalyticsController::class, 'exportTalentReport'])->name('analytics.talent-report.export');

            // Social & Collaborative Learning (BRD §5.9) — moderasi. Phase 4.
            Route::delete('/forum/{id}', [ForumModerationController::class, 'destroyThread'])->name('forum.threads.destroy');
            Route::delete('/forum/{threadId}/balasan/{id}', [ForumModerationController::class, 'destroyReply'])->name('forum.replies.destroy');

            // Live Learning & Mentoring (BRD §5.10) — Phase 4.
            Route::get('/sesi-live', [LiveSessionController::class, 'index'])->name('live-sessions.index');
            Route::post('/sesi-live', [LiveSessionController::class, 'store'])->name('live-sessions.store');
            Route::post('/sesi-live/{id}', [LiveSessionController::class, 'update'])->name('live-sessions.update');
            Route::get('/sesi-live/{id}/peserta', [LiveSessionController::class, 'participants'])->name('live-sessions.participants');
            Route::post('/sesi-live/{sessionId}/peserta/{employeeId}/hadir', [LiveSessionController::class, 'markAttended'])->name('live-sessions.attend');

            // Evaluasi Pelatihan Level 1-4 (BRD §5.5) — Phase 4.
            Route::get('/evaluasi/{enrollmentId}', [TrainingEvaluationController::class, 'show'])->name('evaluations.show');
            Route::post('/evaluasi/{enrollmentId}', [TrainingEvaluationController::class, 'update'])->name('evaluations.update');
            Route::get('/evaluasi-pre-post', [TrainingEvaluationController::class, 'prePostReport'])->name('evaluations.pre-post-report');
            Route::get('/evaluasi-pre-post/ekspor', [TrainingEvaluationController::class, 'exportPrePostReport'])->name('evaluations.pre-post-report.export');
        });

    Route::get('/pegawai/absensi', [AttendanceRecapController::class, 'index'])
        ->middleware('permission:attendance-recap.view')
        ->name('hr.attendance-recap');
    Route::get('/pegawai/absensi/ekspor', [AttendanceRecapController::class, 'exportCsv'])
        ->middleware('permission:attendance-recap.view')
        ->name('hr.attendance-recap.export');

    // SEC-2026-08: hr_admin (admin cabang) TIDAK BOLEH men-generate atau
    // menyetujui payroll SAMA SEKALI — hanya Human Capital (hr_approver,
    // BANK_WIDE) berwenang lewat admin.payroll-generate-bulk. Rute
    // hr.payroll.index/store (generate per-kantor oleh hr_admin) SENGAJA
    // DIHAPUS TOTAL, bukan hanya disembunyikan.
    //
    // PayrollDeductionController di bawah ini BUKAN pengecualian dari
    // itu — wewenangnya SEMPIT dan BERBEDA: input potongan pada draf
    // yang SUDAH dibuat HC, hanya selama status='draft' pada kantornya
    // sendiri (404 di luar itu — tertutup total begitu di-approve,
    // sampai hr_approver membuka kembali lewat payroll-reopen).
    Route::prefix('pegawai/payroll')->name('hr.payroll-deduction.')->middleware('permission:payroll-deduction.manage')->group(function () {
        Route::get('/', [PayrollDeductionController::class, 'index'])->name('index');
        Route::get('/{run}', [PayrollDeductionController::class, 'show'])->name('show');
        Route::post('/{run}/pegawai/{payslip}/potongan', [PayrollDeductionController::class, 'store'])->name('store');
        Route::delete('/{run}/pegawai/{payslip}/potongan/{deduction}', [PayrollDeductionController::class, 'destroy'])->name('destroy');
        Route::post('/{run}/pegawai/{payslip}/tambahan', [PayrollDeductionController::class, 'storeAddition'])->name('store-tambahan');
        Route::delete('/{run}/pegawai/{payslip}/tambahan/{addition}', [PayrollDeductionController::class, 'destroyAddition'])->name('destroy-tambahan');
        Route::get('/{run}/template', [PayrollDeductionController::class, 'template'])->name('template');
        Route::post('/{run}/impor', [PayrollDeductionController::class, 'importAction'])->name('import');
    });

    // hr_admin: kantornya sendiri (OFFICE). hr_approver: seluruh bank
    // (BANK_WIDE) — lihat OvertimeRecapController.
    Route::get('/pegawai/lembur-biaya', [OvertimeRecapController::class, 'index'])
        ->middleware('permission:overtime-recap.view')
        ->name('hr.overtime-recap');
    Route::get('/pegawai/lembur-biaya/ekspor', [OvertimeRecapController::class, 'exportCsv'])
        ->middleware('permission:overtime-recap.view')
        ->name('hr.overtime-recap.export');

    // Pembayaran Lembur MASSAL (Admin Cabang) — HANYA cabang/KCP
    // miliknya sendiri. Kantor pusat dibayar Admin HC lewat rute
    // admin.overtime-disbursement-queue di atas.
    Route::get('/pegawai/lembur-pembayaran', [OvertimeDisbursementController::class, 'indexForBranch'])
        ->middleware('permission:overtime-disbursement.branch')
        ->name('hr.overtime-disbursement.index');
    Route::post('/pegawai/lembur-pembayaran/bayar', [OvertimeDisbursementController::class, 'processBatchForBranch'])
        ->middleware('permission:overtime-disbursement.branch')
        ->name('hr.overtime-disbursement.disburse');

    // Pencairan Bekal Cuti MASSAL (Admin Cabang) — HANYA kantornya
    // sendiri. Bank-wide dibayar Admin HC lewat rute admin.bekal-cuti-queue.
    Route::get('/pegawai/bekal-cuti', [BekalCutiDisbursementController::class, 'indexForBranch'])
        ->middleware('permission:bekal-cuti-disbursement.branch')
        ->name('hr.bekal-cuti.index');
    Route::post('/pegawai/bekal-cuti/bayar', [BekalCutiDisbursementController::class, 'processBatchForBranch'])
        ->middleware('permission:bekal-cuti-disbursement.branch')
        ->name('hr.bekal-cuti.disburse');

    // Pencairan SPPD (Admin Cabang) — HANYA kantornya sendiri.
    // Bank-wide dicairkan Admin HC lewat rute admin.sppd-disbursement-queue.
    Route::get('/pegawai/sppd-pencairan', [SppdDisbursementController::class, 'indexForBranch'])
        ->middleware('permission:sppd-disbursement.branch')
        ->name('hr.sppd-disbursement.index');
    Route::post('/pegawai/sppd-pencairan/{id}/cairkan', [SppdDisbursementController::class, 'disburseForBranch'])
        ->middleware('permission:sppd-disbursement.branch')
        ->name('hr.sppd-disbursement.disburse');

    // Lingkup BANK_WIDE, hanya-baca, independen (§6.3).
    Route::get('/log-audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-log.view')
        ->name('audit.index');
    Route::get('/log-audit/ekspor', [AuditLogController::class, 'exportCsv'])
        ->middleware('permission:audit-log.view')
        ->name('audit.index.export');

    // Dashboard dasar (TOR Fase I) — lingkup BANK_WIDE, hr_approver saja.
    Route::get('/dasbor-hc', [HcDashboardController::class, 'index'])
        ->middleware('permission:hc-dashboard.view')
        ->name('hc.dashboard');

    // Bagan struktur organisasi PER kantor/divisi, hanya-baca — SYSADMIN
    // & Admin HC. index() = pemilih unit, show() = bagan visual,
    // pdf() = unduhan (dompdf, sudah dipakai Slip Gaji).
    Route::prefix('struktur-organisasi')->name('org-chart.')->middleware('permission:org-chart.view')->group(function () {
        Route::get('/', [OrganizationChartController::class, 'index'])->name('index');
        Route::get('/{officeId}', [OrganizationChartController::class, 'show'])->name('show');
        Route::get('/{officeId}/pdf', [OrganizationChartController::class, 'pdf'])->name('pdf');
    });

    // Lingkup teknis (akun login) — BUKAN proses bisnis SDM, lihat
    // Role::SystemAdmin. Reset kata sandi TETAP hardcode system_admin
    // SAJA (kunci gerbang keamanan akun/IT, TIDAK ikut migrasi ke
    // permission dinamis — lihat plan hak akses dinamis).
    Route::prefix('admin/sistem')->name('sysadmin.')->middleware('role:system_admin')->group(function () {
        Route::post('/pengguna/{user}/reset-kata-sandi', [SystemAdminUserController::class, 'resetPassword'])
            ->name('users.reset-password');
    });

    // 13 rute IT lain (parameter/skala gaji/tarif SPPD/geofence/impor
    // absensi mesin/Data Pegawai maker SYSADMIN) — DIKONVERSI ke
    // permission dinamis (sysadmin-it.manage), berbeda dari
    // reset-kata-sandi di atas yang tetap hardcode.
    Route::prefix('admin/sistem')->name('sysadmin.')->middleware('permission:sysadmin-it.manage')->group(function () {
        Route::get('/parameter', [SystemParameterController::class, 'index'])->name('parameters.index');
        Route::get('/parameter/{id}/riwayat', [SystemParameterController::class, 'history'])->name('parameters.history');
        Route::post('/parameter/{id}/nilai', [SystemParameterController::class, 'addValue'])->name('parameters.add-value');

        Route::get('/skala-gaji', [SalaryScaleController::class, 'index'])->name('salary-scale.index');
        Route::post('/skala-gaji', [SalaryScaleController::class, 'addValue'])->name('salary-scale.store');

        Route::get('/tarif-sppd', [SppdTariffAdminController::class, 'index'])->name('sppd-tariffs.index');
        Route::post('/tarif-sppd', [SppdTariffAdminController::class, 'addValue'])->name('sppd-tariffs.store');

        Route::get('/kantor-geofence', [OfficeGeofenceController::class, 'index'])->name('office-geofence.index');
        Route::post('/kantor-geofence/{id}', [OfficeGeofenceController::class, 'update'])->name('office-geofence.update');

        Route::get('/absensi-mesin', [AttendanceDeviceImportController::class, 'index'])->name('attendance-device.index');
        // throttle:10,1 — mengunggah+mengurai berkas CSV besar (maks 10MB)
        // per baris jauh lebih berat daripada permintaan biasa.
        Route::post('/absensi-mesin/impor', [AttendanceDeviceImportController::class, 'import'])
            ->middleware('throttle:10,1')
            ->name('attendance-device.import');

        // SYSADMIN sebagai maker KEDUA data pegawai (BANK_WIDE) —
        // usulan tetap menunggu hr_approver (checker), lihat
        // SystemAdminEmployeeController.
        Route::get('/pegawai', [SystemAdminEmployeeController::class, 'index'])->name('employees.index');
        Route::get('/pegawai/tambah', [SystemAdminEmployeeController::class, 'create'])->name('employees.create');
        Route::post('/pegawai', [SystemAdminEmployeeController::class, 'store'])->name('employees.store');
        Route::get('/pegawai/{id}/ubah', [SystemAdminEmployeeController::class, 'edit'])->name('employees.edit');
        Route::post('/pegawai/{id}/ubah', [SystemAdminEmployeeController::class, 'update'])->name('employees.update');

        // Impor massal — jalur maker SAMA (lihat ImportNewEmployeeRequests),
        // tiap baris tetap satu emp_new_employee_requests pending, TIDAK
        // pernah menulis emp_employees langsung (maker-checker §6.3 utuh).
        Route::get('/pegawai/impor', [EmployeeImportController::class, 'index'])->name('employees.import.index');
        Route::get('/pegawai/impor/contoh', [EmployeeImportController::class, 'template'])->name('employees.import.template');
        // throttle:10,1 — pola sama impor absensi mesin (CSV besar per
        // baris lebih berat daripada permintaan biasa).
        Route::post('/pegawai/impor', [EmployeeImportController::class, 'import'])
            ->middleware('throttle:10,1')
            ->name('employees.import.store');
    });

    // Manajemen peran pengguna DAN Peta Peran (edit izin per peran) —
    // KUNCI GERBANG, TETAP hardcode system_admin|hr_approver (TIDAK
    // ikut migrasi ke permission dinamis, lihat docblock
    // RoleFeatureMapController: kapabilitas "kelola siapa-boleh-apa"
    // tidak boleh bisa mengunci-dirinya-sendiri lewat sistem yang sama
    // yang dia kelola).
    Route::prefix('admin/sistem')->name('sysadmin.')->middleware('role:system_admin|hr_approver')->group(function () {
        Route::get('/pengguna', [SystemAdminUserController::class, 'index'])->name('users.index');
        Route::post('/pengguna/{user}/peran', [SystemAdminUserController::class, 'assignRole'])
            ->name('users.assign-role');
        Route::post('/pengguna/{user}/peran/{role}/cabut', [SystemAdminUserController::class, 'revokeRole'])
            ->name('users.revoke-role');

        Route::get('/peta-peran', [RoleFeatureMapController::class, 'index'])->name('role-map.index');
        Route::post('/peta-peran', [RoleFeatureMapController::class, 'update'])->name('role-map.update');
    });

    // Fitur baru yang eksplisit disebut "SYSADMIN dan Admin HC"
    // (kalender libur, pola/penugasan shift, formasi kantor) —
    // DIKONVERSI ke permission dinamis (sysadmin-content.manage).
    Route::prefix('admin/sistem')->name('sysadmin.')->middleware('permission:sysadmin-content.manage')->group(function () {
        Route::get('/kalender-libur', [NationalHolidayController::class, 'index'])->name('holidays.index');
        Route::post('/kalender-libur', [NationalHolidayController::class, 'store'])->name('holidays.store');
        Route::delete('/kalender-libur/{id}', [NationalHolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::get('/pola-shift', [ShiftPatternController::class, 'index'])->name('shift-patterns.index');
        Route::post('/pola-shift', [ShiftPatternController::class, 'store'])->name('shift-patterns.store');
        Route::post('/pola-shift/{id}', [ShiftPatternController::class, 'update'])->name('shift-patterns.update');
        Route::delete('/pola-shift/{id}', [ShiftPatternController::class, 'destroy'])->name('shift-patterns.destroy');

        Route::get('/penugasan-shift', [ShiftAssignmentController::class, 'index'])->name('shift-assignments.index');
        Route::post('/penugasan-shift', [ShiftAssignmentController::class, 'store'])->name('shift-assignments.store');

        Route::get('/formasi-kantor', [OfficeFormasiController::class, 'index'])->name('office-formasi.index');
        Route::post('/formasi-kantor/{id}', [OfficeFormasiController::class, 'update'])->name('office-formasi.update');

        // Daftar Kantor/Jabatan — rujukan tunggal dipakai Data Pegawai, SK,
        // Payroll, dst (lihat OfficeController/PositionController).
        // Tidak bisa dihapus, hanya dinonaktifkan (is_active).
        Route::get('/daftar-kantor', [OfficeController::class, 'index'])->name('offices.index');
        Route::post('/daftar-kantor', [OfficeController::class, 'store'])->name('offices.store');
        Route::post('/daftar-kantor/{id}', [OfficeController::class, 'update'])->name('offices.update');

        Route::get('/daftar-jabatan', [PositionController::class, 'index'])->name('positions.index');
        Route::post('/daftar-jabatan', [PositionController::class, 'store'])->name('positions.store');
        Route::post('/daftar-jabatan/{id}', [PositionController::class, 'update'])->name('positions.update');

        // Daftar Akun Jurnal — dipakai memilih akun beban/penampungan
        // pajak saat memproses batch pembayaran lembur massal.
        Route::get('/akun-jurnal', [JournalAccountController::class, 'index'])->name('journal-accounts.index');
        Route::post('/akun-jurnal', [JournalAccountController::class, 'store'])->name('journal-accounts.store');
        Route::post('/akun-jurnal/{id}', [JournalAccountController::class, 'update'])->name('journal-accounts.update');
    });
});
