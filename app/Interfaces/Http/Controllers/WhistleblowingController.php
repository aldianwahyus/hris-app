<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Whistleblowing\Application\SubmitReport;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** "Pengaduan" ESS (Whistleblowing, Fase 2) — kirim laporan + riwayat laporan MILIK SENDIRI (non-anonim saja, lihat SubmitReport). */
final class WhistleblowingController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly SubmitReport $submit,
    ) {}

    public function index(): View
    {
        $employeeId = $this->requireEmployeeId();

        $reports = DB::table('wb_reports')
            ->where('reporter_employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();

        return view('ess.whistleblowing', ['reports' => $reports, 'categories' => SubmitReport::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employeeId = $this->requireEmployeeId();

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(SubmitReport::CATEGORIES))],
            'description' => ['required', 'string', 'max:5000'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        try {
            $this->submit->handle(
                $validated['category'],
                $validated['description'],
                $isAnonymous,
                $employeeId,
                new AuditActor(
                    actorId: $employeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        $pesan = $isAnonymous
            ? 'Laporan anonim terkirim. Karena dikirim anonim, laporan ini TIDAK akan muncul di riwayat Anda.'
            : 'Laporan terkirim, akan ditindaklanjuti hr_approver.';

        return redirect()->route('whistleblowing.index')->with('sukses', $pesan);
    }

    private function requireEmployeeId(): string
    {
        $employeeId = $this->actor->employeeId();
        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        return $employeeId;
    }
}
