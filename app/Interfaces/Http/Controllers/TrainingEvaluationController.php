<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Interfaces\Http\Support\CsvExport;
use App\Modules\Access\Contracts\CurrentActor;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evaluasi Pelatihan Level 3+4 (BRD §5.5) — HC
 * (permission:lms-catalog.manage). Level 3 (feedback atasan) diisi HC
 * (BUKAN atasan langsung pegawai itu sendiri — simplifikasi disengaja,
 * lihat plan). Level 4 SENGAJA kualitatif (impact_notes, teks bebas) —
 * app ini tidak punya sistem KPI terukur, mengarang angka KPI akan
 * jadi data palsu (alasan sama seperti ROI Analytics Phase 3).
 * Level 2 (pre/post test) lihat prePostReport() — pakai ulang
 * Assessment Center, bukan mesin ujian baru.
 */
final class TrainingEvaluationController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function show(string $enrollmentId): View
    {
        $enrollment = DB::table('lms_enrollments as en')
            ->join('emp_employees as e', 'e.id', '=', 'en.employee_id')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('en.id', $enrollmentId)
            ->select('en.*', 'e.full_name', 'e.nrp', 'c.title as course_title')
            ->first();

        abort_if($enrollment === null, 404);

        $evaluation = DB::table('lms_training_evaluations')->where('enrollment_id', $enrollmentId)->first();

        return view('admin.lms-training-evaluation', compact('enrollment', 'evaluation'));
    }

    public function update(Request $request, string $enrollmentId): RedirectResponse
    {
        $enrollment = DB::table('lms_enrollments')->where('id', $enrollmentId)->first();
        abort_if($enrollment === null, 404);

        $validated = $request->validate([
            'behavior_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'behavior_comments' => ['nullable', 'string'],
            'impact_notes' => ['nullable', 'string'],
        ]);

        $now = new DateTimeImmutable;
        $assessedBy = $this->actor->employeeId();
        $existing = DB::table('lms_training_evaluations')->where('enrollment_id', $enrollmentId)->first();

        $payload = [
            'behavior_score' => $validated['behavior_score'] ?? null,
            'behavior_comments' => $validated['behavior_comments'] ?? null,
            'behavior_assessed_by' => $assessedBy,
            'behavior_assessed_at' => $now,
            'impact_notes' => $validated['impact_notes'] ?? null,
            'impact_assessed_by' => $assessedBy,
            'impact_assessed_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing === null) {
            DB::table('lms_training_evaluations')->insert([
                'id' => (string) Uuid7::generate(),
                'enrollment_id' => $enrollmentId,
                ...$payload,
                'created_at' => $now,
                'version' => 1,
            ]);
        } else {
            DB::table('lms_training_evaluations')->where('id', $existing->id)->update([
                ...$payload,
                'version' => $existing->version + 1,
            ]);
        }

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_training_evaluation',
            auditableId: $enrollmentId,
            action: AuditAction::Updated,
            newValues: $validated,
        ));

        return redirect()->route('lms.admin.evaluations.show', $enrollmentId)->with('sukses', 'Evaluasi tersimpan.');
    }

    public function prePostReport(): View
    {
        return view('admin.lms-pre-post-report', ['rows' => $this->prePostRows()]);
    }

    public function exportPrePostReport(): StreamedResponse
    {
        return CsvExport::download(
            'laporan-pre-post-test-'.now()->format('Y-m-d').'.csv',
            ['Nama Pegawai', 'Skor Pre-Test', 'Skor Post-Test', 'Selisih'],
            $this->prePostRows()->map(fn ($r) => [
                $r->full_name,
                $r->pre_score ?? '-',
                $r->post_score ?? '-',
                $r->delta ?? '-',
            ])->all(),
        );
    }

    /** @return Collection<int, object{full_name: mixed, pre_score: mixed, post_score: mixed, delta: ?float}> */
    private function prePostRows(): Collection
    {
        $grouped = DB::table('lms_assessment_attempts as at')
            ->join('lms_assessments as a', 'a.id', '=', 'at.assessment_id')
            ->join('emp_employees as e', 'e.id', '=', 'at.employee_id')
            ->whereIn('a.evaluation_type', ['pre_test', 'post_test'])
            ->where('at.status', 'scored')
            ->select('e.id as employee_id', 'e.full_name', 'a.course_id', 'a.evaluation_type', 'at.total_score', 'at.scored_at')
            ->orderBy('e.full_name')
            ->get()
            ->groupBy(fn ($row) => $row->employee_id.':'.$row->course_id)
            ->map(function ($group) {
                // (array) $group->first() — groupBy() menjamin grup
                // TIDAK PERNAH kosong, tapi PHPStan tidak bisa tahu itu
                // dari tipe stdClass|null yang umum dipakai Collection::first().
                $first = (array) $group->first();
                $pre = $group->firstWhere('evaluation_type', 'pre_test');
                $post = $group->firstWhere('evaluation_type', 'post_test');

                return (object) [
                    'full_name' => $first['full_name'],
                    'pre_score' => $pre->total_score ?? null,
                    'post_score' => $post->total_score ?? null,
                    'delta' => ($pre !== null && $post !== null) ? round($post->total_score - $pre->total_score, 2) : null,
                ];
            })
            ->values();

        /** @var Collection<int, object{full_name: mixed, pre_score: mixed, post_score: mixed, delta: ?float}> $rows */
        $rows = $grouped;

        return $rows;
    }

    private function currentAuditActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
