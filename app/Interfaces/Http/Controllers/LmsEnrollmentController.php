<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Lms\Application\CancelEnrollment;
use App\Modules\Lms\Application\ComputeLearningPathProgress;
use App\Modules\Lms\Application\EnrollInCourseBatch;
use App\Modules\Lms\Application\RecommendCoursesForGap;
use App\Modules\Lms\Application\SubmitTrainingEvaluation;
use App\Shared\Audit\Domain\AuditActor;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * ESS — jelajah jadwal pelatihan terbuka, daftar, lihat pendaftaran
 * sendiri, unduh sertifikat. Lingkup SELF murni (pola sama
 * ShiftSwapRequestController/PayslipController) — tanpa
 * middleware permission, semua pegawai boleh mendaftar.
 */
final class LmsEnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollInCourseBatch $enroll,
        private readonly RecommendCoursesForGap $recommend,
        private readonly ComputeLearningPathProgress $pathProgress,
        private readonly SubmitTrainingEvaluation $submitEvaluation,
        private readonly CancelEnrollment $cancel,
    ) {}

    public function cancelEnrollment(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->cancel->handle(
                enrollmentId: $id,
                employeeId: $user->employee_id,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'Pendaftaran pelatihan berhasil dibatalkan.');
    }

    public function developmentPlan(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $progress = $this->pathProgress->forEmployee($user->employee_id);

        return view('lms.my-development-plan', compact('progress'));
    }

    /**
     * Evaluasi Pelatihan Level 1 — Kepuasan peserta (BRD §5.5). Lingkup
     * SELF: hanya pemilik pendaftaran yang boleh mengisi.
     */
    public function evaluate(Request $request, string $enrollmentId): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $enrollment = DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('en.id', $enrollmentId)
            ->where('en.employee_id', $user->employee_id)
            ->select('en.*', 'c.title as course_title')
            ->first();

        abort_if($enrollment === null, 404);
        abort_if($enrollment->completion_status === null, 404, 'Evaluasi hanya dapat diisi setelah pelatihan selesai dinilai.');

        $existing = DB::table('lms_training_evaluations')->where('enrollment_id', $enrollmentId)->first();

        return view('lms.evaluate-training', compact('enrollment', 'existing'));
    }

    public function storeEvaluation(Request $request, string $enrollmentId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'satisfaction_score' => ['required', 'integer', 'min:1', 'max:5'],
            'satisfaction_comments' => ['nullable', 'string'],
        ]);

        try {
            $this->submitEvaluation->handle(
                enrollmentId: $enrollmentId,
                employeeId: $user->employee_id,
                satisfactionScore: $validated['satisfaction_score'],
                satisfactionComments: $validated['satisfaction_comments'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.mine')->with('sukses', 'Terima kasih atas evaluasi Anda.');
    }

    public function index(Request $request): View
    {
        $today = now()->format('Y-m-d');

        $user = $request->user();
        $recommendations = ($user !== null && $user->employee_id !== null)
            ? $this->recommend->forEmployee($user->employee_id)
            : [];

        $batches = DB::table('lms_course_batches as b')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('b.status', 'scheduled')
            ->where(fn ($q) => $q->whereNull('b.registration_deadline')->orWhere('b.registration_deadline', '>=', $today))
            ->select(
                'b.id', 'b.batch_code', 'b.location', 'b.instructor_name',
                'b.start_date', 'b.end_date', 'b.registration_deadline', 'b.capacity',
                'c.title as course_title', 'c.description as course_description', 'c.category',
            )
            ->orderBy('b.start_date')
            ->get()
            ->map(function ($batch) {
                $batch->taken_seats = DB::table('lms_enrollments')
                    ->where('batch_id', $batch->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                return $batch;
            });

        return view('lms.index', compact('batches', 'recommendations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'uuid', 'exists:lms_course_batches,id'],
        ]);

        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $enrollmentNumber = $this->enroll->handle(
                employeeId: $user->employee_id,
                batchId: $validated['batch_id'],
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('lms.mine')
            ->with('sukses', "Pendaftaran {$enrollmentNumber} berhasil dikirim dan menunggu persetujuan.");
    }

    public function mine(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $enrollments = DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('en.employee_id', $user->employee_id)
            ->select(
                'en.id', 'en.enrollment_number', 'en.status', 'en.completion_status', 'en.decision_note',
                'en.score', 'en.certificate_number', 'en.requested_at',
                'c.title as course_title', 'b.batch_code', 'b.start_date', 'b.end_date', 'b.id as batch_id',
            )
            ->orderByDesc('en.requested_at')
            ->get();

        // Ringkasan kehadiran "X/Y hari" (BRD §5.3 Absensi) — dihitung per
        // batch (jumlah sesi) vs per enrollment (jumlah hadir), bukan
        // N+1 query per baris.
        $batchIds = $enrollments->pluck('batch_id')->unique()->values();
        $sessionCounts = DB::table('lms_course_sessions')
            ->whereIn('batch_id', $batchIds)
            ->select('batch_id', DB::raw('count(*) as jumlah'))
            ->groupBy('batch_id')
            ->pluck('jumlah', 'batch_id');
        $presentCounts = DB::table('lms_attendances')
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('status', 'hadir')
            ->select('enrollment_id', DB::raw('count(*) as jumlah'))
            ->groupBy('enrollment_id')
            ->pluck('jumlah', 'enrollment_id');

        $evaluatedEnrollmentIds = DB::table('lms_training_evaluations')
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereNotNull('satisfaction_score')
            ->pluck('enrollment_id')
            ->all();

        $enrollments->each(function ($en) use ($sessionCounts, $presentCounts, $evaluatedEnrollmentIds) {
            $en->total_sesi = $sessionCounts[$en->batch_id] ?? 0;
            $en->hadir_sesi = $presentCounts[$en->id] ?? 0;
            $en->is_evaluated = in_array($en->id, $evaluatedEnrollmentIds, true);
        });

        return view('lms.mine', compact('enrollments'));
    }

    public function certificate(Request $request, string $id): Response
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $enrollment = DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->where('en.id', $id)
            ->where('en.employee_id', $user->employee_id)
            ->select(
                'en.enrollment_number', 'en.certificate_number', 'en.completion_status',
                'en.score', 'en.completed_at',
                'c.title as course_title', 'b.batch_code', 'b.start_date', 'b.end_date',
                'e.full_name', 'e.nrp',
            )
            ->first();

        abort_if($enrollment === null, 404);
        abort_if($enrollment->completion_status !== 'lulus', 404, 'Sertifikat belum tersedia — pendaftaran ini belum dinyatakan lulus.');

        $pdf = Pdf::loadView('lms.certificate-pdf', ['en' => $enrollment]);

        // certificate_number mengandung "/" (format SERT/{kode}/{tahun}/{urut})
        // — TIDAK valid langsung sebagai nama berkas unduhan (Content-Disposition
        // menolaknya), jadi disanitasi di sini SAJA, bukan pada kolom aslinya.
        $safeFilename = str_replace('/', '-', $enrollment->certificate_number);

        return $pdf->download("sertifikat-{$safeFilename}.pdf");
    }
}
