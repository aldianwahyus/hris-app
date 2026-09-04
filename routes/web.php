<?php

declare(strict_types=1);

use App\Interfaces\Http\Controllers\ApplicationPipelineController;
use App\Interfaces\Http\Controllers\ApprovalQueueController;
use App\Interfaces\Http\Controllers\AssessmentController;
use App\Interfaces\Http\Controllers\AssetAssignmentController;
use App\Interfaces\Http\Controllers\AssetController;
use App\Interfaces\Http\Controllers\AttendanceDeviceImportController;
use App\Interfaces\Http\Controllers\AttendanceRecapController;
use App\Interfaces\Http\Controllers\AuditLogController;
use App\Interfaces\Http\Controllers\BekalCutiDisbursementController;
use App\Interfaces\Http\Controllers\BranchDashboardController;
use App\Interfaces\Http\Controllers\CompanySettingsController;
use App\Interfaces\Http\Controllers\CompetencyController;
use App\Interfaces\Http\Controllers\DashboardController;
use App\Interfaces\Http\Controllers\DecisionLetterController;
use App\Interfaces\Http\Controllers\DocumentRequestController;
use App\Interfaces\Http\Controllers\DocumentRequestQueueController;
use App\Interfaces\Http\Controllers\EmployeeApprovalQueueController;
use App\Interfaces\Http\Controllers\EmployeeAwardController;
use App\Interfaces\Http\Controllers\EmployeeCertificationController;
use App\Interfaces\Http\Controllers\EmployeeCompetencyController;
use App\Interfaces\Http\Controllers\EmployeeContractController;
use App\Interfaces\Http\Controllers\EmployeeCvController;
use App\Interfaces\Http\Controllers\EmployeeDirectoryController;
use App\Interfaces\Http\Controllers\EmployeeExternalWorkHistoryController;
use App\Interfaces\Http\Controllers\EmployeeFamilyMemberController;
use App\Interfaces\Http\Controllers\EmployeeHealthRecordController;
use App\Interfaces\Http\Controllers\EmployeeImportController;
use App\Interfaces\Http\Controllers\EmployeeInternalWorkHistoryController;
use App\Interfaces\Http\Controllers\EmployeeOrganizationController;
use App\Interfaces\Http\Controllers\EmployeePositionRecordController;
use App\Interfaces\Http\Controllers\EmployeeTrainingController;
use App\Interfaces\Http\Controllers\ForumModerationController;
use App\Interfaces\Http\Controllers\GamificationController;
use App\Interfaces\Http\Controllers\HcDashboardController;
use App\Interfaces\Http\Controllers\HelpdeskController;
use App\Interfaces\Http\Controllers\HelpdeskQueueController;
use App\Interfaces\Http\Controllers\IncomeRecapController;
use App\Interfaces\Http\Controllers\IzinApprovalController;
use App\Interfaces\Http\Controllers\JobOfferController;
use App\Interfaces\Http\Controllers\JobPostingController;
use App\Interfaces\Http\Controllers\JobRequisitionController;
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
use App\Interfaces\Http\Controllers\MobileMenuSettingsController;
use App\Interfaces\Http\Controllers\NationalHolidayController;
use App\Interfaces\Http\Controllers\NotificationController;
use App\Interfaces\Http\Controllers\OffboardingController;
use App\Interfaces\Http\Controllers\OffboardingQueueController;
use App\Interfaces\Http\Controllers\OfficeController;
use App\Interfaces\Http\Controllers\OfficeFormasiController;
use App\Interfaces\Http\Controllers\OfficeGeofenceController;
use App\Interfaces\Http\Controllers\OfficeImportController;
use App\Interfaces\Http\Controllers\OnboardingProgressController;
use App\Interfaces\Http\Controllers\OnboardingTemplateController;
use App\Interfaces\Http\Controllers\OrganizationChartController;
use App\Interfaces\Http\Controllers\OutsideAttendanceApprovalController;
use App\Interfaces\Http\Controllers\OvertimeDisbursementController;
use App\Interfaces\Http\Controllers\OvertimeRecapController;
use App\Interfaces\Http\Controllers\PayrollApprovalController;
use App\Interfaces\Http\Controllers\PositionController;
use App\Interfaces\Http\Controllers\PrivacyController;
use App\Interfaces\Http\Controllers\PrivacyRequestQueueController;
use App\Interfaces\Http\Controllers\PublicCareersController;
use App\Interfaces\Http\Controllers\ReportBuilderController;
use App\Interfaces\Http\Controllers\RoleFeatureMapController;
use App\Interfaces\Http\Controllers\SalaryScaleController;
use App\Interfaces\Http\Controllers\SecuritySettingsController;
use App\Interfaces\Http\Controllers\ShiftAssignmentController;
use App\Interfaces\Http\Controllers\ShiftPatternController;
use App\Interfaces\Http\Controllers\ShiftSwapApprovalController;
use App\Interfaces\Http\Controllers\SignatureController;
use App\Interfaces\Http\Controllers\SppdApprovalController;
use App\Interfaces\Http\Controllers\SppdDisbursementController;
use App\Interfaces\Http\Controllers\SppdMemoController;
use App\Interfaces\Http\Controllers\SppdPaymentBatchController;
use App\Interfaces\Http\Controllers\SppdTariffAdminController;
use App\Interfaces\Http\Controllers\SuccessionPlanController;
use App\Interfaces\Http\Controllers\SurveyAdminController;
use App\Interfaces\Http\Controllers\SurveyController;
use App\Interfaces\Http\Controllers\SystemAdminEmployeeController;
use App\Interfaces\Http\Controllers\SystemAdminSessionController;
use App\Interfaces\Http\Controllers\SystemAdminUserController;
use App\Interfaces\Http\Controllers\SystemHealthController;
use App\Interfaces\Http\Controllers\SystemParameterController;
use App\Interfaces\Http\Controllers\TalentProfileController;
use App\Interfaces\Http\Controllers\TrainingEvaluationController;
use App\Interfaces\Http\Controllers\WhistleblowingController;
use App\Interfaces\Http\Controllers\WhistleblowingQueueController;
use App\Interfaces\Http\Controllers\WorkforceAnalyticsController;
use App\Modules\Access\Interfaces\Http\Controllers\LoginController;
use App\Modules\Access\Interfaces\Http\Controllers\LogoutController;
use App\Modules\Access\Interfaces\Http\Controllers\TwoFactorController;
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

    // 2FA (TOTP) — Fase 2 (evaluasi PM/client 2026-09-03). Dijangkau HANYA
    // setelah LoginController::store() menaruh sesi "menggantung" — masih
    // di bawah middleware 'guest' karena Auth::check() TETAP false sampai
    // TwoFactorController::completeLogin()/confirmSetup() memanggil
    // FinalizeLogin. Literal /setup WAJIB terdaftar sebelum route lain
    // yang mirip agar tidak tertelan (tidak relevan di sini karena tidak
    // ada wildcard, tapi konsisten dengan pola urutan rute proyek ini).
    Route::get('/2fa/verifikasi', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
    Route::post('/2fa/verifikasi', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.challenge.verify');
    Route::get('/2fa/setup', [TwoFactorController::class, 'showSetup'])->name('two-factor.setup');
    Route::post('/2fa/setup', [TwoFactorController::class, 'confirmSetup'])->name('two-factor.setup.confirm');
});

Route::post('/keluar', LogoutController::class)->middleware('auth')->name('logout');

// Rekrutmen (ATS) — halaman karier PUBLIK, modul baru (evaluasi
// PM/client 2026-09-02). SENGAJA di LUAR grup 'auth' (siapa pun bisa
// melamar tanpa akun HCIS) — SATU throttle:30,1 untuk seluruh grup
// (pola PERSIS /masuk: pagar tambahan kasar, bukan pembatas halus
// per rute — RateLimiter bawaan Laravel membagi SATU bilik hitung
// per IP+domain lintas middleware throttle apa pun, jadi menumpuk
// tingkatan berbeda di sini hanya akan membuat rute GET biasa ikut
// menghabiskan jatah rute POST tanpa manfaat nyata).
Route::middleware('throttle:30,1')->group(function () {
    Route::prefix('lowongan')->name('careers.')->group(function () {
        Route::get('/', [PublicCareersController::class, 'index'])->name('index');
        Route::get('/{id}', [PublicCareersController::class, 'show'])->name('show');
        Route::post('/{id}/lamar', [PublicCareersController::class, 'apply'])->name('apply');
    });
    Route::get('/tawaran/{token}', [PublicCareersController::class, 'offerForm'])->name('careers.offer');
    Route::post('/tawaran/{token}', [PublicCareersController::class, 'respondToOffer'])->name('careers.offer-respond');

    // Portal status kandidat (Fase 2) — PUBLIK, TANPA login, lihat
    // SubmitApplication (status_token) + PublicCareersController::statusPage().
    Route::get('/lowongan/status/{token}', [PublicCareersController::class, 'statusPage'])->name('careers.status');
});

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

    // Lonceng notifikasi — lingkup SELF murni, sama seperti beranda di
    // atas, cermin NotificationApiController mobile.
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/{id}/baca', [NotificationController::class, 'markAsRead'])->name('notifikasi.baca');

    // Tanda Tangan Elektronik (internal) — generik lintas jenis dokumen,
    // otorisasi per jenis ditegakkan DI DALAM SignatureController
    // (bukan middleware permission tunggal di sini), lihat resolveContext().
    Route::post('/tanda-tangan/{signableType}/{signableId}', [SignatureController::class, 'store'])->name('signature.store');

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

        // Foto profil — rute TERPISAH dari ess.cv.update (form data
        // pribadi teks) karena ini unggah berkas, bukan field teks.
        // photo() menampilkan inline (BEDA dari pdf()/downloadSk() yang
        // selalu memaksa unduh) supaya bisa dipakai langsung sebagai
        // src <img>.
        Route::get('/foto', [EmployeeCvController::class, 'photo'])->name('photo');
        Route::post('/foto', [EmployeeCvController::class, 'updatePhoto'])->name('photo.update');
        Route::delete('/foto', [EmployeeCvController::class, 'removePhoto'])->name('photo.destroy');

        Route::post('/pelatihan', [EmployeeTrainingController::class, 'store'])->name('trainings.store');
        Route::delete('/pelatihan/{id}', [EmployeeTrainingController::class, 'destroy'])->name('trainings.destroy');

        Route::post('/sertifikasi', [EmployeeCertificationController::class, 'store'])->name('certifications.store');
        Route::delete('/sertifikasi/{id}', [EmployeeCertificationController::class, 'destroy'])->name('certifications.destroy');

        Route::post('/organisasi', [EmployeeOrganizationController::class, 'store'])->name('organizations.store');
        Route::delete('/organisasi/{id}', [EmployeeOrganizationController::class, 'destroy'])->name('organizations.destroy');

        Route::post('/penghargaan', [EmployeeAwardController::class, 'store'])->name('awards.store');
        Route::delete('/penghargaan/{id}', [EmployeeAwardController::class, 'destroy'])->name('awards.destroy');
    });

    // "Sesi Aktif Saya" (Fase 2) — lingkup SELF murni, TIDAK butuh
    // permission (siapa pun yang login boleh kelola sesinya sendiri).
    Route::prefix('keamanan-saya')->name('security-settings.')->group(function () {
        Route::get('/', [SecuritySettingsController::class, 'index'])->name('index');
        Route::post('/{id}/cabut', [SecuritySettingsController::class, 'revoke'])->name('revoke');
        Route::post('/cabut-lainnya', [SecuritySettingsController::class, 'revokeOthers'])->name('revoke-others');
    });

    // "Privasi Data Saya" (UU PDP, Fase 2) — lingkup SELF murni, TIDAK
    // butuh permission (siapa pun yang login boleh unduh/ajukan hapus
    // datanya sendiri), lihat PrivacyController.
    Route::prefix('privasi-saya')->name('privacy.')->group(function () {
        Route::get('/', [PrivacyController::class, 'index'])->name('index');
        Route::get('/unduh', [PrivacyController::class, 'exportData'])->name('export');
        Route::post('/hapus', [PrivacyController::class, 'requestDeletion'])->name('request-deletion');
    });

    // Tahap 2 — Pengajuan dari Layar. Selalu atas nama pegawai yang
    // sedang masuk (ownership); tidak ada parameter employee_id di sini.
    Route::prefix('cuti')->name('leave.')->group(function () {
        Route::get('/ajukan', [LeaveRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [LeaveRequestController::class, 'store'])->name('store');
        Route::get('/riwayat', [LeaveRequestController::class, 'history'])->name('history');
        // Batal HANYA saat status='pending' (sebelum tahap 1 diputus) —
        // ditegakkan di CancelLeaveRequest, bukan di sini.
        Route::post('/{id}/batal', [LeaveRequestController::class, 'cancelRequest'])->name('cancel');
    });

    Route::prefix('lembur')->name('overtime.')->group(function () {
        Route::get('/ajukan', [OvertimeRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [OvertimeRequestController::class, 'store'])->name('store');
        Route::get('/riwayat', [OvertimeRequestController::class, 'history'])->name('history');
        // Batal HANYA saat status='pending' (sebelum tahap 1 diputus) —
        // ditegakkan di CancelOvertimeRequest, bukan di sini.
        Route::post('/{id}/batal', [OvertimeRequestController::class, 'cancelRequest'])->name('cancel');
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

    // Aset Saya — baca saja, lingkup SELF (lihat AssetAssignmentController::mine()).
    Route::get('/aset-saya', [AssetAssignmentController::class, 'mine'])->name('assets.mine');

    // Tahap 6 — SPPD (BPP/442/03/64/2026). Selalu atas nama pegawai yang
    // sedang masuk (ownership); tidak ada parameter employee_id di sini.
    Route::prefix('sppd')->name('sppd.')->group(function () {
        Route::get('/ajukan', [SppdRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [SppdRequestController::class, 'store'])->name('store');
        Route::get('/riwayat', [SppdRequestController::class, 'history'])->name('history');
        // Batal HANYA saat status='pending' (sebelum tahap 1 diputus) —
        // ditegakkan di CancelSppdRequest, bukan di sini.
        Route::post('/{id}/batal', [SppdRequestController::class, 'cancelRequest'])->name('cancel');
    });

    // Tukar Shift — selalu atas nama pegawai yang login (pemohon).
    // Rekan yang dituju TIDAK dimintai konfirmasi terpisah, langsung ke
    // antrean Atasan Langsung (1 tahap, lihat ShiftSwapApprovalController).
    Route::prefix('tukar-shift')->name('shift.')->group(function () {
        Route::get('/ajukan', [ShiftSwapRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [ShiftSwapRequestController::class, 'store'])->name('store');
        Route::get('/riwayat', [ShiftSwapRequestController::class, 'history'])->name('history');
        // Batal HANYA saat status='pending' (Tukar Shift satu tahap
        // saja) — ditegakkan di CancelShiftSwapRequest, bukan di sini.
        Route::post('/{id}/batal', [ShiftSwapRequestController::class, 'cancelRequest'])->name('cancel');
    });

    // Izin Tidak Masuk Bekerja — TERPISAH dari Cuti (tidak menyentuh
    // leave_balances), langsung ke antrean Atasan Langsung (1 tahap,
    // lihat IzinApprovalController), pola SAMA PERSIS Tukar Shift.
    Route::prefix('izin')->name('izin.')->group(function () {
        Route::get('/ajukan', [IzinRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [IzinRequestController::class, 'store'])->name('store');
        Route::get('/{id}/lampiran', [IzinRequestController::class, 'downloadAttachment'])->name('attachment');
        // Batal HANYA saat status='pending' (Izin satu tahap saja) —
        // ditegakkan di CancelIzinRequest, bukan di sini.
        Route::post('/{id}/batal', [IzinRequestController::class, 'cancelRequest'])->name('cancel');
    });

    // Layanan Dokumen Mandiri — modul baru (evaluasi PM/client
    // 2026-09-02), lingkup SELF murni, pola sama Izin di atas.
    Route::prefix('dokumen')->name('documents.')->group(function () {
        Route::get('/ajukan', [DocumentRequestController::class, 'create'])->name('create');
        Route::post('/ajukan', [DocumentRequestController::class, 'store'])->name('store');
        Route::get('/riwayat', [DocumentRequestController::class, 'history'])->name('history');
        Route::get('/{id}/unduh', [DocumentRequestController::class, 'download'])->name('download');
    });

    // HR Helpdesk / Case Management — modul baru (evaluasi PM/client
    // 2026-09-02), lingkup SELF murni. Tiket + balasan dua arah, lihat
    // HelpdeskQueueController untuk sisi HC.
    Route::prefix('bantuan')->name('helpdesk.')->group(function () {
        Route::get('/', [HelpdeskController::class, 'index'])->name('index');
        Route::get('/ajukan', [HelpdeskController::class, 'create'])->name('create');
        Route::post('/ajukan', [HelpdeskController::class, 'store'])->name('store');
        Route::get('/{id}', [HelpdeskController::class, 'show'])->name('show');
        Route::post('/{id}/balas', [HelpdeskController::class, 'reply'])->name('reply');
    });

    // Survei Keterlibatan (eNPS/Pulse) — modul baru (evaluasi PM/client
    // 2026-09-02), lingkup SELF: survei bank_wide ATAU scope kantor
    // pegawai, lihat SurveyController::eligibleSurvey().
    Route::prefix('survei')->name('survey.')->group(function () {
        Route::get('/', [SurveyController::class, 'index'])->name('index');
        Route::get('/{id}', [SurveyController::class, 'fill'])->name('fill');
        Route::post('/{id}', [SurveyController::class, 'submit'])->name('submit');
    });

    // Whistleblowing/Pengaduan — modul baru (Fase 2), lingkup SELF
    // murni (riwayat HANYA laporan non-anonim milik sendiri — laporan
    // anonim SENGAJA tidak tertaut ke pegawai mana pun, lihat
    // SubmitReport). Antrean penanganan ada di
    // WhistleblowingQueueController (hr_approver saja).
    Route::prefix('pengaduan')->name('whistleblowing.')->group(function () {
        Route::get('/', [WhistleblowingController::class, 'index'])->name('index');
        Route::post('/', [WhistleblowingController::class, 'store'])->name('store');
    });

    // Offboarding — modul baru (evaluasi PM/client 2026-09-02). HANYA
    // wawancara keluar yang berupa ESS (pengajuan/keputusan/clearance
    // murni admin, lihat OffboardingQueueController) — lingkup SELF,
    // dibatasi ke pemisahan MILIK SENDIRI berstatus 'approved'.
    Route::prefix('wawancara-keluar')->name('offboarding.')->group(function () {
        Route::get('/', [OffboardingController::class, 'exitInterviewForm'])->name('exit-interview-form');
        Route::post('/', [OffboardingController::class, 'storeExitInterview'])->name('exit-interview-store');
    });

    // Pelatihan (LMS) — pegawai jelajah jadwal terbuka & mendaftar
    // sendiri, langsung ke antrean Atasan Langsung (1 tahap, lihat
    // LmsEnrollmentApprovalController) — pola sama Tukar Shift.
    Route::prefix('pelatihan')->name('lms.')->group(function () {
        Route::get('/', [LmsEnrollmentController::class, 'index'])->name('index');
        Route::post('/daftar', [LmsEnrollmentController::class, 'store'])->name('store');
        Route::get('/saya', [LmsEnrollmentController::class, 'mine'])->name('mine');
        Route::get('/sertifikat/{id}', [LmsEnrollmentController::class, 'certificate'])->name('certificate');
        // Batal HANYA saat status='pending' (satu tahap saja) —
        // ditegakkan di CancelEnrollment, bukan di sini.
        Route::post('/{id}/batal', [LmsEnrollmentController::class, 'cancelEnrollment'])->name('cancel');

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
            Route::get('/{id}/cetak/lampiran-penerima', [OvertimeDisbursementController::class, 'printLampiranPenerima'])->name('print-lampiran-penerima');
        });

        // Pembayaran SPPD Massal (Admin HC) — batch di-scope PER GRUP MEMO
        // (bukan per divisi seperti Lembur, lihat ProcessSppdPaymentBatch).
        // Cabang dibayar Admin Cabang lewat rute terpisah di bawah (prefix
        // pegawai/), lihat SppdPaymentBatchController.
        Route::prefix('sppd-massal-pembayaran')->middleware('permission:sppd-payment-batch.hc')->name('sppd-payment.')->group(function () {
            Route::get('/', [SppdPaymentBatchController::class, 'indexForHc'])->name('groups');
            Route::get('/{memoGroupId}', [SppdPaymentBatchController::class, 'showMemoQueue'])->name('queue');
            Route::post('/{memoGroupId}/bayar', [SppdPaymentBatchController::class, 'processBatchForHc'])->name('process');
        });

        // Detail batch + cetak — diakses HC MAUPUN Admin Cabang, pembatasan
        // halus ditegakkan di controller (guardBatchAccess()), bukan di sini.
        Route::prefix('sppd-massal-pembayaran/batch')->name('sppd-payment-batch.')->middleware('permission:sppd-payment-batch.hc|sppd-payment-batch.branch')->group(function () {
            Route::get('/{id}', [SppdPaymentBatchController::class, 'showBatch'])->name('show');
            Route::get('/{id}/cetak/nota-debet', [SppdPaymentBatchController::class, 'printNotaDebet'])->name('print-nota-debet');
            Route::get('/{id}/cetak/jurnal-slip', [SppdPaymentBatchController::class, 'printJurnalSlip'])->name('print-jurnal-slip');
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
            Route::get('/{id}/cetak/lampiran-penerima', [BekalCutiDisbursementController::class, 'printLampiranPenerima'])->name('print-lampiran-penerima');
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

        // Layanan Dokumen Mandiri — modul baru (evaluasi PM/client
        // 2026-09-02), SATU tahap (pola PERSIS Izin di atas).
        Route::get('/dokumen', [DocumentRequestQueueController::class, 'index'])
            ->middleware('permission:document-request.manage')
            ->name('document-request-queue');
        Route::post('/dokumen/{id}/setujui', [DocumentRequestQueueController::class, 'issue'])
            ->middleware('permission:document-request.manage')
            ->name('document-request-issue');
        Route::post('/dokumen/{id}/tolak', [DocumentRequestQueueController::class, 'reject'])
            ->middleware('permission:document-request.manage')
            ->name('document-request-reject');
        Route::get('/dokumen/{id}/unduh', [DocumentRequestQueueController::class, 'download'])
            ->middleware('permission:document-request.manage')
            ->name('document-request-download');

        // HR Helpdesk / Case Management — modul baru (evaluasi PM/client
        // 2026-09-02), SATU tahap. hr_admin lingkup kantornya sendiri,
        // hr_approver seluruh bank — lihat HelpdeskQueueController.
        Route::get('/bantuan', [HelpdeskQueueController::class, 'index'])
            ->middleware('permission:helpdesk.manage')
            ->name('helpdesk-queue');
        Route::get('/bantuan/{id}', [HelpdeskQueueController::class, 'show'])
            ->middleware('permission:helpdesk.manage')
            ->name('helpdesk-show');
        Route::post('/bantuan/{id}/balas', [HelpdeskQueueController::class, 'reply'])
            ->middleware('permission:helpdesk.manage')
            ->name('helpdesk-reply');
        Route::post('/bantuan/{id}/tugaskan', [HelpdeskQueueController::class, 'assign'])
            ->middleware('permission:helpdesk.manage')
            ->name('helpdesk-assign');
        Route::post('/bantuan/{id}/status', [HelpdeskQueueController::class, 'updateStatus'])
            ->middleware('permission:helpdesk.manage')
            ->name('helpdesk-status');

        // Survei Keterlibatan (eNPS/Pulse) — modul baru (evaluasi PM/client
        // 2026-09-02). hr_admin kantornya sendiri + bank-wide, hr_approver
        // seluruhnya — lihat SurveyAdminController::scopedSurvey().
        Route::get('/survei', [SurveyAdminController::class, 'index'])
            ->middleware('permission:survey.manage')
            ->name('survey-index');
        Route::get('/survei/buat', [SurveyAdminController::class, 'create'])
            ->middleware('permission:survey.manage')
            ->name('survey-create');
        Route::post('/survei/buat', [SurveyAdminController::class, 'store'])
            ->middleware('permission:survey.manage')
            ->name('survey-store');
        Route::get('/survei/{id}', [SurveyAdminController::class, 'show'])
            ->middleware('permission:survey.manage')
            ->name('survey-show');
        Route::post('/survei/{id}/terbitkan', [SurveyAdminController::class, 'publish'])
            ->middleware('permission:survey.manage')
            ->name('survey-publish');
        Route::post('/survei/{id}/tutup', [SurveyAdminController::class, 'close'])
            ->middleware('permission:survey.manage')
            ->name('survey-close');

        // Onboarding Terstruktur — modul baru (evaluasi PM/client
        // 2026-09-02). Template routes (literal) WAJIB terdaftar SEBELUM
        // wildcard /onboarding/{id} — pola sama Impor Kantor (bug
        // ditemukan sebelumnya bila wildcard didaftar lebih dulu).
        Route::get('/onboarding/template', [OnboardingTemplateController::class, 'index'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-template-index');
        Route::get('/onboarding/template/buat', [OnboardingTemplateController::class, 'create'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-template-create');
        Route::post('/onboarding/template/buat', [OnboardingTemplateController::class, 'store'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-template-store');
        Route::post('/onboarding/template/{id}/toggle', [OnboardingTemplateController::class, 'toggleActive'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-template-toggle');
        Route::get('/onboarding', [OnboardingProgressController::class, 'index'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-index');
        Route::get('/onboarding/{id}', [OnboardingProgressController::class, 'show'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-show');
        Route::post('/onboarding/{checklistId}/item/{itemId}', [OnboardingProgressController::class, 'completeItem'])
            ->middleware('permission:onboarding.manage')
            ->name('onboarding-item-complete');

        // Offboarding — modul baru (evaluasi PM/client 2026-09-02).
        // Maker-checker (pola PERSIS pegawai-baru): hr_admin/hr_approver
        // ajukan, hr_approver putuskan. Literal /buat WAJIB sebelum
        // wildcard /{id} — pola sama Impor Kantor.
        Route::get('/offboarding', [OffboardingQueueController::class, 'index'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-index');
        Route::get('/offboarding/buat', [OffboardingQueueController::class, 'create'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-create');
        Route::post('/offboarding/buat', [OffboardingQueueController::class, 'store'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-store');
        Route::get('/offboarding/{id}', [OffboardingQueueController::class, 'show'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-show');
        Route::post('/offboarding/{id}/setujui', [OffboardingQueueController::class, 'approve'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-approve');
        Route::post('/offboarding/{id}/tolak', [OffboardingQueueController::class, 'reject'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-reject');
        Route::post('/offboarding/{separationId}/item/{itemId}', [OffboardingQueueController::class, 'completeItem'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-item-complete');
        Route::post('/offboarding/{id}/tuntaskan', [OffboardingQueueController::class, 'markComplete'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-complete');
        Route::post('/offboarding/{id}/wawancara-keluar', [OffboardingQueueController::class, 'storeExitInterview'])
            ->middleware('permission:offboarding.manage')
            ->name('offboarding-exit-interview-store');

        // Rekrutmen (ATS) — modul baru (evaluasi PM/client 2026-09-02),
        // TERBESAR dari 9 modul. Requisition pakai permission TERPISAH
        // (recruitment-requisition.decide, hr_approver saja) — beda dari
        // operasional ATS sehari-hari (recruitment.manage). Literal
        // /buat WAJIB sebelum wildcard /{id} — pola sama modul lain.
        Route::get('/rekrutmen/requisition', [JobRequisitionController::class, 'index'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-requisition-index');
        Route::get('/rekrutmen/requisition/buat', [JobRequisitionController::class, 'create'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-requisition-create');
        Route::post('/rekrutmen/requisition/buat', [JobRequisitionController::class, 'store'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-requisition-store');
        Route::get('/rekrutmen/requisition/{id}', [JobRequisitionController::class, 'show'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-requisition-show');
        Route::post('/rekrutmen/requisition/{id}/setujui', [JobRequisitionController::class, 'approve'])
            ->middleware('permission:recruitment-requisition.decide')
            ->name('recruitment-requisition-approve');
        Route::post('/rekrutmen/requisition/{id}/tolak', [JobRequisitionController::class, 'reject'])
            ->middleware('permission:recruitment-requisition.decide')
            ->name('recruitment-requisition-reject');

        Route::get('/rekrutmen/lowongan', [JobPostingController::class, 'index'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-posting-index');
        Route::get('/rekrutmen/lowongan/buat', [JobPostingController::class, 'create'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-posting-create');
        Route::post('/rekrutmen/lowongan/buat', [JobPostingController::class, 'store'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-posting-store');
        Route::post('/rekrutmen/lowongan/{id}/tutup', [JobPostingController::class, 'close'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-posting-close');
        Route::get('/rekrutmen/lowongan/{postingId}/pipeline', [ApplicationPipelineController::class, 'index'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-pipeline-index');

        Route::get('/rekrutmen/lamaran/{id}', [ApplicationPipelineController::class, 'show'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-show');
        Route::get('/rekrutmen/lamaran/{id}/cv', [ApplicationPipelineController::class, 'downloadResume'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-resume');
        Route::post('/rekrutmen/lamaran/{id}/tahap', [ApplicationPipelineController::class, 'updateStage'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-stage');
        Route::post('/rekrutmen/lamaran/{id}/wawancara', [ApplicationPipelineController::class, 'scheduleInterview'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-interview');
        Route::post('/rekrutmen/lamaran/{id}/wawancara/{interviewId}/feedback', [ApplicationPipelineController::class, 'recordInterviewFeedback'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-interview-feedback');
        Route::get('/rekrutmen/lamaran/{id}/wawancara/{interviewId}/ics', [ApplicationPipelineController::class, 'downloadInterviewIcs'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-interview-ics');
        Route::post('/rekrutmen/lamaran/{id}/tawaran', [JobOfferController::class, 'store'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-offer');
        Route::post('/rekrutmen/lamaran/{id}/jadikan-pegawai', [ApplicationPipelineController::class, 'convertToEmployee'])
            ->middleware('permission:recruitment.manage')
            ->name('recruitment-application-convert');

        // Perkakas Data Pribadi (UU PDP, Fase 2) — peninjauan permintaan
        // penghapusan data, HANYA hr_approver (bank-wide, lihat migrasi
        // permission privacy-request.manage).
        Route::get('/privasi', [PrivacyRequestQueueController::class, 'index'])
            ->middleware('permission:privacy-request.manage')
            ->name('privacy-request-queue');
        Route::post('/privasi/{id}/tinjau', [PrivacyRequestQueueController::class, 'review'])
            ->middleware('permission:privacy-request.manage')
            ->name('privacy-request-review');
        Route::post('/privasi/{id}/tolak', [PrivacyRequestQueueController::class, 'reject'])
            ->middleware('permission:privacy-request.manage')
            ->name('privacy-request-reject');
        Route::post('/privasi/{id}/tuntaskan', [PrivacyRequestQueueController::class, 'complete'])
            ->middleware('permission:privacy-request.manage')
            ->name('privacy-request-complete');

        // Whistleblowing/Pengaduan — HANYA hr_approver (permission
        // TERPISAH dari hr_admin, lihat migrasi permission
        // whistleblowing.manage). Literal /buat tidak ada di sini
        // (pengajuan murni ESS, lihat WhistleblowingController).
        Route::get('/pengaduan', [WhistleblowingQueueController::class, 'index'])
            ->middleware('permission:whistleblowing.manage')
            ->name('whistleblowing-queue');
        Route::get('/pengaduan/{id}', [WhistleblowingQueueController::class, 'show'])
            ->middleware('permission:whistleblowing.manage')
            ->name('whistleblowing-show');
        Route::post('/pengaduan/{id}/proses', [WhistleblowingQueueController::class, 'startProcessing'])
            ->middleware('permission:whistleblowing.manage')
            ->name('whistleblowing-start-processing');
        Route::post('/pengaduan/{id}/selesai', [WhistleblowingQueueController::class, 'complete'])
            ->middleware('permission:whistleblowing.manage')
            ->name('whistleblowing-complete');

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

            // Manajemen Kontrak (pegawai kontrak/outsource) — modul baru
            // (evaluasi PM/client 2026-09-02), pola SAMA sub-resource di atas.
            Route::post('/kontrak', [EmployeeContractController::class, 'store'])->name('contract.store');
            Route::post('/kontrak/{contractId}/perpanjang', [EmployeeContractController::class, 'renew'])->name('contract.renew');
            Route::post('/kontrak/{contractId}/status', [EmployeeContractController::class, 'updateStatus'])->name('contract.status');
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

    // SPPD Massal — input berbasis memo divisi (banyak pegawai sekaligus,
    // langsung disetujui), TERPISAH SEPENUHNYA dari jalur SPPD mandiri
    // (sppd.*/sppd-approval-queue/sppd-disbursement-queue di atas, TIDAK
    // disentuh). Lingkup bank-wide/kantor sendiri ditegakkan di
    // SppdMemoController (pola sama sk.*), bukan di middleware ini.
    Route::prefix('sppd-massal')
        ->middleware('permission:sppd-memo.manage')
        ->name('sppd-memo.')
        ->group(function () {
            Route::get('/', [SppdMemoController::class, 'index'])->name('index');
            Route::get('/buat', [SppdMemoController::class, 'create'])->name('create');
            Route::post('/', [SppdMemoController::class, 'store'])->name('store');
            Route::get('/{id}', [SppdMemoController::class, 'show'])->name('show');
            Route::get('/{id}/cetak/surat-jalan', [SppdMemoController::class, 'printSuratJalan'])->name('print-surat-jalan');
            Route::get('/{id}/cetak/rincian-lumpsum/{requestId}', [SppdMemoController::class, 'printRincianLumpsum'])->name('print-rincian-lumpsum');
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

    Route::get('/pegawai/dasbor', [BranchDashboardController::class, 'index'])
        ->middleware('permission:branch-dashboard.view')
        ->name('hr.dashboard');

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

    // Rekap Penghasilan — gaji+lembur+SPPD+bekal cuti dijumlah per
    // pegawai per bulan, lingkup SAMA overtime-recap.view (hr_admin:
    // kantornya sendiri, hr_approver: seluruh bank) — lihat
    // IncomeRecapController.
    Route::get('/pegawai/penghasilan', [IncomeRecapController::class, 'index'])
        ->middleware('permission:income-recap.view')
        ->name('hr.income-recap');
    Route::get('/pegawai/penghasilan/ekspor', [IncomeRecapController::class, 'exportCsv'])
        ->middleware('permission:income-recap.view')
        ->name('hr.income-recap.export');

    // Report Builder (Fase 2) — registry subjek (ReportSubjectRegistry),
    // lingkup SAMA income-recap.view (hr_admin: kantornya sendiri,
    // hr_approver: seluruh bank) — lihat ReportBuilderController.
    Route::prefix('laporan')->name('hr.report-builder.')->middleware('permission:report-builder.manage')->group(function () {
        Route::get('/', [ReportBuilderController::class, 'index'])->name('index');
        Route::get('/{subjectKey}', [ReportBuilderController::class, 'show'])->name('show');
        Route::get('/{subjectKey}/unduh', [ReportBuilderController::class, 'download'])->name('download');
    });

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

    // Pembayaran SPPD Massal (Admin Cabang) — HANYA kantornya sendiri.
    // Bank-wide dibayar Admin HC lewat rute admin.sppd-payment.groups.
    Route::prefix('pegawai/sppd-massal-pembayaran')->middleware('permission:sppd-payment-batch.branch')->name('hr.sppd-payment.')->group(function () {
        Route::get('/', [SppdPaymentBatchController::class, 'indexForBranch'])->name('groups');
        Route::get('/{memoGroupId}', [SppdPaymentBatchController::class, 'showMemoQueue'])->name('queue');
        Route::post('/{memoGroupId}/bayar', [SppdPaymentBatchController::class, 'processBatchForBranch'])->name('process');
    });

    // Lingkup BANK_WIDE, hanya-baca, independen (§6.3).
    Route::get('/log-audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-log.view')
        ->name('audit.index');
    Route::get('/log-audit/ekspor', [AuditLogController::class, 'exportCsv'])
        ->middleware('permission:audit-log.view')
        ->name('audit.index.export');

    // Dashboard dasar (TOR Fase I) — lingkup BANK_WIDE, hr_approver + system_admin.
    Route::get('/dasbor-hc', [HcDashboardController::class, 'index'])
        ->middleware('permission:hc-dashboard.view')
        ->name('hc.dashboard');

    // Analitik Tenaga Kerja (Fase 2) — BERBASIS ATURAN, BUKAN machine
    // learning, lihat WorkforceAnalyticsController.
    Route::get('/analitik-tenaga-kerja', [WorkforceAnalyticsController::class, 'index'])
        ->middleware('permission:workforce-analytics.view')
        ->name('hc.workforce-analytics');

    // Record Pegawai — laporan rincian posisi terakhir per bulan, BANK_WIDE,
    // TANPA form input (murni dari SK Mutasi/Promosi yang disetujui).
    Route::get('/record-pegawai', [EmployeePositionRecordController::class, 'index'])
        ->middleware('permission:employee-position-record.view')
        ->name('admin.employee-position-record');

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
        // 2FA (Fase 2) — pola SAMA reset-kata-sandi di atas: kunci
        // gerbang keamanan akun, TETAP hardcode system_admin, TIDAK
        // ikut migrasi ke permission dinamis.
        Route::post('/pengguna/{user}/reset-2fa', [SystemAdminUserController::class, 'resetTwoFactor'])
            ->name('users.reset-two-factor');

        // Manajemen Sesi Aktif (Fase 2) — kelas kewenangan SAMA baris di
        // atas (lihat docblock SystemAdminSessionController).
        Route::get('/sesi', [SystemAdminSessionController::class, 'index'])->name('sessions.index');
        Route::post('/sesi/{id}/cabut', [SystemAdminSessionController::class, 'revoke'])->name('sessions.revoke');

        // Dashboard Kesehatan Sistem (Fase 2) — hanya-baca, tapi tetap
        // hardcode system_admin (menyingkap detail infrastruktur internal:
        // versi/host DB, status Redis, isi log error mentah).
        Route::get('/kesehatan-sistem', [SystemHealthController::class, 'index'])->name('system-health.index');
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

        // Impor massal kantor — TIDAK ada antrean persetujuan (beda dari
        // impor pegawai), baris yang lolos LANGSUNG aktif, lihat
        // ImportOffices/OfficeImportController. WAJIB terdaftar SEBELUM
        // offices.update ({id} wildcard) di bawah — urutan terbalik
        // membuat POST /daftar-kantor/impor tertangkap sebagai {id}="impor"
        // (bug ditemukan lewat pengujian: "invalid input syntax for type
        // uuid: impor").
        Route::get('/daftar-kantor/impor', [OfficeImportController::class, 'index'])->name('offices.import.index');
        Route::get('/daftar-kantor/impor/contoh', [OfficeImportController::class, 'template'])->name('offices.import.template');
        Route::post('/daftar-kantor/impor', [OfficeImportController::class, 'import'])
            ->middleware('throttle:10,1')
            ->name('offices.import.store');

        Route::post('/daftar-kantor/{id}', [OfficeController::class, 'update'])->name('offices.update');

        Route::get('/daftar-jabatan', [PositionController::class, 'index'])->name('positions.index');
        Route::post('/daftar-jabatan', [PositionController::class, 'store'])->name('positions.store');
        Route::post('/daftar-jabatan/{id}', [PositionController::class, 'update'])->name('positions.update');

        // Daftar Akun Jurnal — dipakai memilih akun beban/penampungan
        // pajak saat memproses batch pembayaran lembur massal.
        Route::get('/akun-jurnal', [JournalAccountController::class, 'index'])->name('journal-accounts.index');
        Route::post('/akun-jurnal', [JournalAccountController::class, 'store'])->name('journal-accounts.store');
        Route::post('/akun-jurnal/{id}', [JournalAccountController::class, 'update'])->name('journal-accounts.update');

        // Kontrol menu Aplikasi Mobile — satu saklar per menu, BANK-WIDE
        // (lihat MobileMenuSettingsController).
        Route::get('/menu-mobile', [MobileMenuSettingsController::class, 'index'])->name('mobile-menu.index');
        Route::post('/menu-mobile', [MobileMenuSettingsController::class, 'update'])->name('mobile-menu.update');

        // Pengaturan Perusahaan (Fase 2) — nama+lambang dinamis, lihat
        // CompanyProfile/CompanySettingsController.
        Route::get('/pengaturan-perusahaan', [CompanySettingsController::class, 'index'])->name('company-settings.index');
        Route::post('/pengaturan-perusahaan', [CompanySettingsController::class, 'update'])->name('company-settings.update');
    });

    // Manajemen Aset — permission TERPISAH dari sysadmin-content.manage
    // (hr_admin: kantornya sendiri, hr_approver/system_admin: seluruh
    // bank — lihat AssetController::index()).
    Route::prefix('admin/sistem')->name('sysadmin.')->middleware('permission:asset.manage')->group(function () {
        Route::get('/aset', [AssetController::class, 'index'])->name('assets.index');
        Route::post('/aset', [AssetController::class, 'store'])->name('assets.store');
        Route::post('/aset/{id}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('/aset/{id}/tugaskan', [AssetAssignmentController::class, 'assign'])->name('assets.assign');
        Route::post('/penugasan-aset/{id}/kembalikan', [AssetAssignmentController::class, 'returnAsset'])->name('assets.return');
    });
});
