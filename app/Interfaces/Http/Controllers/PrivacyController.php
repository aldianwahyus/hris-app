<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Privacy\Application\ExportEmployeeData;
use App\Modules\Privacy\Application\RequestDataDeletion;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** "Privasi Data Saya" (UU PDP, Fase 2) — lingkup SELF murni. */
final class PrivacyController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ExportEmployeeData $export,
        private readonly RequestDataDeletion $requestDeletion,
    ) {}

    public function index(): View
    {
        $employeeId = $this->requireEmployeeId();

        $requests = DB::table('pdp_deletion_requests')
            ->where('employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();

        return view('ess.privacy', ['requests' => $requests]);
    }

    public function exportData(): JsonResponse
    {
        $employeeId = $this->requireEmployeeId();
        $data = $this->export->handle($employeeId);

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="data-saya-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $employeeId = $this->requireEmployeeId();

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->requestDeletion->handle($employeeId, $validated['reason'], new AuditActor(
                actorId: $employeeId,
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('privacy.index')->with('sukses', 'Permintaan penghapusan data terkirim, menunggu peninjauan hr_approver.');
    }

    private function requireEmployeeId(): string
    {
        $employeeId = $this->actor->employeeId();
        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        return $employeeId;
    }
}
