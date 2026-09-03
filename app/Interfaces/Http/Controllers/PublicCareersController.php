<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Recruitment\Application\RespondToJobOffer;
use App\Modules\Recruitment\Application\SubmitApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman karier PUBLIK — TANPA login (lihat routes/web.php, grup
 * terpisah di luar middleware 'auth', dilindungi throttle). Siapa
 * pun dapat menjelajah lowongan terbuka, melamar, dan merespons
 * tawaran lewat tautan token — TIDAK ada data pegawai/internal yang
 * terekspos di sini.
 */
final class PublicCareersController extends Controller
{
    public function __construct(
        private readonly SubmitApplication $submitApplication,
        private readonly RespondToJobOffer $respondToOffer,
    ) {}

    public function index(): View
    {
        $postings = DB::table('rec_job_postings as jp')
            ->join('rec_job_requisitions as r', 'r.id', '=', 'jp.requisition_id')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->where('jp.is_open', true)
            ->select('jp.id', 'jp.title', 'jp.employment_status_offered', 'jp.opened_at', 'o.name as office_name')
            ->orderByDesc('jp.opened_at')
            ->get();

        return view('careers.index', ['postings' => $postings]);
    }

    public function show(string $id): View
    {
        $posting = DB::table('rec_job_postings as jp')
            ->join('rec_job_requisitions as r', 'r.id', '=', 'jp.requisition_id')
            ->join('md_offices as o', 'o.id', '=', 'r.office_id')
            ->where('jp.id', $id)
            ->where('jp.is_open', true)
            ->select('jp.id', 'jp.title', 'jp.description', 'jp.requirements', 'jp.employment_status_offered', 'o.name as office_name')
            ->first();

        abort_if($posting === null, 404, 'Lowongan tidak ditemukan atau sudah ditutup.');

        return view('careers.show', ['posting' => $posting]);
    }

    public function apply(Request $request, string $id): RedirectResponse|View
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'resume.mimes' => 'CV hanya boleh berformat PDF, DOC, atau DOCX.',
            'resume.max' => 'Ukuran CV maksimal 5 MB.',
        ]);

        $resumePath = null;

        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('rekrutmen/cv', 's3') ?: null;
        }

        try {
            $this->submitApplication->handle(
                postingId: $id,
                fullName: $validated['full_name'],
                email: $validated['email'],
                phone: $validated['phone'] ?? null,
                resumePath: $resumePath,
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return view('careers.apply-success');
    }

    public function offerForm(string $token): View
    {
        $offer = DB::table('rec_job_offers as o')
            ->join('rec_applications as a', 'a.id', '=', 'o.application_id')
            ->join('rec_candidates as c', 'c.id', '=', 'a.candidate_id')
            ->join('md_positions as p', 'p.id', '=', 'o.proposed_position_id')
            ->join('md_offices as of', 'of.id', '=', 'o.proposed_office_id')
            ->where('o.response_token', $token)
            ->select('o.status', 'o.proposed_salary_notes', 'o.offered_at', 'o.responded_at', 'c.full_name', 'p.name as position_name', 'of.name as office_name')
            ->first();

        abort_if($offer === null, 404, 'Tautan tawaran tidak valid.');

        return view('careers.offer', ['offer' => $offer, 'token' => $token]);
    }

    public function respondToOffer(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate(['keputusan' => ['required', 'string', 'in:terima,tolak']]);

        try {
            $this->respondToOffer->handle($token, $validated['keputusan'] === 'terima');
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('careers.offer', $token)->with('sukses', 'Respons Anda telah tercatat. Terima kasih.');
    }
}
