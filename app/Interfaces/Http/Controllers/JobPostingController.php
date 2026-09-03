<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Recruitment\Application\PublishJobPosting;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Lowongan (job posting) — dibuka dari requisition yang disetujui. */
final class JobPostingController extends Controller
{
    private const EMPLOYMENT_STATUSES = ['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsource'];

    public function __construct(private readonly PublishJobPosting $publish) {}

    public function index(): View
    {
        $postings = DB::table('rec_job_postings as jp')
            ->join('rec_job_requisitions as r', 'r.id', '=', 'jp.requisition_id')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->select('jp.id', 'jp.title', 'jp.is_open', 'jp.opened_at', 'jp.closed_at', 'o.name as office_name')
            ->orderByDesc('jp.created_at')
            ->get();

        $applicationCounts = DB::table('rec_applications')
            ->select('posting_id', DB::raw('count(*) as jumlah'))
            ->groupBy('posting_id')
            ->pluck('jumlah', 'posting_id');

        return view('admin.recruitment-posting-index', ['postings' => $postings, 'applicationCounts' => $applicationCounts]);
    }

    public function create(): View
    {
        $approvedRequisitions = DB::table('rec_job_requisitions as r')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->join('md_positions as p', 'p.id', '=', 'r.position_id')
            ->whereNotIn('r.id', DB::table('rec_job_postings')->select('requisition_id'))
            ->where('r.status', 'approved')
            ->select('r.id', 'r.requested_headcount', 'o.name as office_name', 'p.name as position_name')
            ->orderByDesc('r.decided_at')
            ->get();

        return view('admin.recruitment-posting-create', ['requisitions' => $approvedRequisitions, 'employmentStatuses' => self::EMPLOYMENT_STATUSES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requisition_id' => ['required', 'uuid', 'exists:rec_job_requisitions,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
            'requirements' => ['required', 'string', 'max:3000'],
            'employment_status_offered' => ['required', 'string', Rule::in(array_keys(self::EMPLOYMENT_STATUSES))],
        ]);

        try {
            $id = $this->publish->handle(
                requisitionId: $validated['requisition_id'],
                title: $validated['title'],
                description: $validated['description'],
                requirements: $validated['requirements'],
                employmentStatusOffered: $validated['employment_status_offered'],
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-posting-index')->with('sukses', 'Lowongan berhasil dibuka: '.$validated['title']);
    }

    public function close(string $id): RedirectResponse
    {
        $posting = DB::table('rec_job_postings')->where('id', $id)->first();
        abort_if($posting === null, 404);

        DB::table('rec_job_postings')->where('id', $id)->update([
            'is_open' => false,
            'closed_at' => new DateTimeImmutable,
            'updated_at' => new DateTimeImmutable,
            'version' => $posting->version + 1,
        ]);

        return redirect()->route('admin.recruitment-posting-index')->with('sukses', 'Lowongan ditutup.');
    }
}
