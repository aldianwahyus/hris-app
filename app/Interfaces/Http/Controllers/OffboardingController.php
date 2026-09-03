<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Offboarding\Application\SubmitExitInterview;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Offboarding — ESS, HANYA wawancara keluar (exit interview), lingkup
 * SELF murni. Pegawai mengisi SENDIRI setelah pengajuan pemisahan
 * miliknya disetujui — HC juga bisa mengisikan lewat
 * OffboardingQueueController::storeExitInterview() (kelas Application
 * SAMA, tidak ada pembedaan siapa yang mengisi pada skema).
 */
final class OffboardingController extends Controller
{
    public function __construct(private readonly SubmitExitInterview $submit) {}

    public function exitInterviewForm(Request $request): View
    {
        $separation = $this->eligibleSeparation($request);

        return view('offboarding.exit-interview', ['separation' => $separation]);
    }

    public function storeExitInterview(Request $request): RedirectResponse
    {
        $separation = $this->eligibleSeparation($request);

        $validated = $request->validate([
            'reason_detail' => ['nullable', 'string', 'max:1000'],
            'satisfaction_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->submit->handle(
                separationId: $separation->id,
                employeeId: $separation->employee_id,
                reasonDetail: $validated['reason_detail'] ?? null,
                satisfactionRating: $validated['satisfaction_rating'] ?? null,
                wouldRecommend: isset($validated['would_recommend']) ? (bool) $validated['would_recommend'] : null,
                comments: $validated['comments'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('ess.dashboard')->with('sukses', 'Terima kasih, wawancara keluar Anda telah tersimpan.');
    }

    /** @return object{id: string, employee_id: string} */
    private function eligibleSeparation(Request $request): object
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $separation = DB::table('off_separation_requests')
            ->where('employee_id', $user->employee_id)
            ->where('status', 'approved')
            ->orderByDesc('decided_at')
            ->first();

        abort_if($separation === null, 404, 'Tidak ada pengajuan pemisahan yang berlaku untuk Anda.');

        $alreadySubmitted = DB::table('off_exit_interviews')->where('separation_id', $separation->id)->exists();
        abort_if($alreadySubmitted, 403, 'Anda sudah pernah mengisi wawancara keluar ini.');

        /** @var object{id: string, employee_id: string} $separation */
        return $separation;
    }
}
