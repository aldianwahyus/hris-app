<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Notifications\RequestDecided;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean Layanan Dokumen Mandiri — HC memproses permintaan pegawai.
 * SATU tahap (pola PERSIS IzinApprovalController): hr_admin lingkup
 * kantornya sendiri, hr_approver seluruh bank.
 */
final class DocumentRequestQueueController extends Controller
{
    private const DOCUMENT_TYPES = [
        'surat_keterangan_kerja' => 'Surat Keterangan Kerja',
        'surat_referensi' => 'Surat Referensi Kerja',
        'surat_keterangan_penghasilan' => 'Surat Keterangan Penghasilan',
        'lainnya' => 'Lainnya',
    ];

    public function __construct(private readonly CurrentActor $actor) {}

    public function index(): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $requests = DB::table('doc_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('r.status', 'pending')
            ->select('r.id', 'r.document_type', 'r.purpose', 'r.created_at', 'e.full_name', 'e.nrp')
            ->orderBy('r.created_at')
            ->get();

        $signedIds = DB::table('sig_signatures')->where('signable_type', 'document_request')->pluck('signable_id');

        $awaitingSignature = DB::table('doc_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('r.status', 'siap')
            ->whereNotIn('r.id', $signedIds)
            ->select('r.id', 'r.document_type', 'r.processed_at', 'e.full_name', 'e.nrp')
            ->orderBy('r.processed_at')
            ->get();

        return view('admin.document-request-queue', [
            'requests' => $requests,
            'awaitingSignature' => $awaitingSignature,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function pendingCount(): int
    {
        if (! $this->actor->hasPermission('document-request.manage')) {
            return 0;
        }

        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        return DB::table('doc_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('r.status', 'pending')
            ->count();
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $note = $request->string('catatan')->toString();

        return $this->decide($id, 'ditolak', $note !== '' ? $note : null, 'Permintaan dokumen ditolak.');
    }

    public function issue(string $id): RedirectResponse
    {
        return $this->decide($id, 'siap', null, 'Dokumen diterbitkan.');
    }

    private function decide(string $id, string $status, ?string $note, string $successMessage): RedirectResponse
    {
        $row = DB::table('doc_requests')->where('id', $id)->first();
        abort_if($row === null, 404);
        abort_unless($row->status === 'pending', 404, 'Permintaan sudah diproses sebelumnya.');

        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $now = new DateTimeImmutable;

        DB::table('doc_requests')->where('id', $id)->update([
            'status' => $status,
            'processed_by' => $actorEmployeeId,
            'processed_at' => $now,
            'decision_note' => $note,
            'updated_at' => $now,
            'version' => $row->version + 1,
        ]);

        $employeeUser = User::query()->where('employee_id', $row->employee_id)->first();
        $documentLabel = self::DOCUMENT_TYPES[$row->document_type] ?? $row->document_type;
        $employeeUser?->notify(new RequestDecided(strtoupper(substr($id, 0, 8)), $documentLabel, $status === 'siap', $note));

        return redirect()->route('admin.document-request-queue')->with('sukses', $successMessage);
    }

    /** HC pratinjau/unduh dokumen 'siap' — lingkup SAMA index() (kantornya sendiri/bank-wide). */
    public function download(string $id): Response
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $row = DB::table('doc_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('r.id', $id)
            ->select('r.*')
            ->first();

        abort_if($row === null, 404);
        abort_unless($row->status === 'siap', 404, 'Dokumen belum diterbitkan.');

        $employee = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $row->employee_id)
            ->select('e.full_name', 'e.nrp', 'e.join_date', 'o.name as office_name')
            ->first();

        $signature = DB::table('sig_signatures')
            ->where('signable_type', 'document_request')
            ->where('signable_id', $row->id)
            ->orderByDesc('signed_at')
            ->first();

        $pdf = Pdf::loadView('documents.letter-pdf', [
            'row' => $row,
            'employee' => $employee,
            'documentTypeLabel' => self::DOCUMENT_TYPES[$row->document_type] ?? $row->document_type,
            'signature' => $signature,
        ]);

        return $pdf->download('dokumen-'.substr($row->id, 0, 8).'.pdf');
    }
}
