<?php

declare(strict_types=1);

namespace App\Modules\Izin\Interfaces\Http\Controllers;

use App\Modules\Izin\Application\CancelIzinRequest;
use App\Modules\Izin\Application\SubmitIzinRequest;
use App\Modules\Izin\Domain\IzinCategory;
use App\Modules\Izin\Interfaces\Http\Requests\SubmitIzinRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Izin Tidak Masuk Bekerja dari ESS — SELALU atas nama pegawai yang
 * sedang masuk (ownership, lingkup SELF). Terpisah total dari Cuti,
 * tidak menyentuh leave_balances sama sekali.
 */
final class IzinRequestController
{
    public function __construct(
        private readonly SubmitIzinRequest $submit,
        private readonly CancelIzinRequest $cancel,
    ) {}

    public function cancelRequest(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->cancel->handle(
                izinRequestId: $id,
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

        return back()->with('sukses', 'Pengajuan izin berhasil dibatalkan.');
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $riwayat = DB::table('izin_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $officeTimezone = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $user->employee_id)
            ->value('o.timezone') ?? 'Asia/Makassar';

        return view('izin.create', [
            'riwayat' => $riwayat,
            'categories' => IzinCategory::cases(),
            'isAdminHc' => $user->hasRole('hr_approver'),
            'todayOffice' => (new DateTimeImmutable('today', new DateTimeZone($officeTimezone)))->format('Y-m-d'),
        ]);
    }

    public function store(SubmitIzinRequestForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $attachmentPath = null;
        $attachmentOriginalName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $stored = $file->store('izin', 's3');

            abort_if($stored === false, 500, 'Gagal mengunggah lampiran — coba lagi.');

            $attachmentPath = $stored;
            $attachmentOriginalName = $file->getClientOriginalName();
        }

        try {
            $requestNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                category: IzinCategory::from($request->string('category')->toString()),
                startDate: new DateTimeImmutable($request->string('start_date')->toString()),
                endDate: new DateTimeImmutable($request->string('end_date')->toString()),
                reason: (string) $request->string('reason'),
                attachmentPath: $attachmentPath,
                attachmentOriginalName: $attachmentOriginalName,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
                isAdminHc: $user->hasRole('hr_approver'),
            );
        } catch (InvalidArgumentException|DomainException|RuntimeException $e) {
            return redirect()->route('izin.create')->with('gagal', $e->getMessage());
        }

        return redirect()->route('izin.create')->with('sukses', "Pengajuan izin {$requestNumber} terkirim, menunggu persetujuan Atasan Langsung.");
    }

    public function downloadAttachment(Request $request, string $id): StreamedResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        // Pemilik pengajuan ATAU siapa pun yang berwenang memutuskannya
        // (Atasan Langsung dalam lingkup) boleh mengunduh — diperiksa di
        // IzinApprovalController untuk jalur admin; di sini HANYA jalur
        // ESS milik sendiri (lingkup SELF, sama seperti downloadSk di
        // EmployeeCvController).
        $row = DB::table('izin_requests')->where('id', $id)->where('employee_id', $user->employee_id)->first();

        abort_if($row === null || $row->attachment_path === null, 404);

        return Storage::disk('s3')->download($row->attachment_path, $row->attachment_original_name);
    }
}
