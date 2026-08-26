<?php

declare(strict_types=1);

namespace App\Modules\Overtime\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Attendance\Contracts\AttendanceRepository;
use App\Modules\Overtime\Application\SubmitOvertimeRequest;
use App\Modules\Overtime\Domain\NoOvertimeEvidence;
use App\Modules\Overtime\Domain\OvertimeType;
use App\Modules\Overtime\Interfaces\Http\Requests\SubmitOvertimeRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class OvertimeRequestController
{
    public function __construct(
        private readonly SubmitOvertimeRequest $submit,
        private readonly AttendanceRepository $attendance,
        private readonly CurrentActor $actor,
    ) {}

    /**
     * Pratinjau bukti lembur (jika tanggal sudah dipilih) dihitung ulang
     * di sini SEMATA untuk ditampilkan — bukan dipercaya saat pengajuan
     * disimpan. store() -> SubmitOvertimeRequest menghitung ulang dari
     * nol secara independen atas catatan absensi, sehingga pratinjau ini
     * tidak bisa dipalsukan lewat devtools untuk mengubah jam yang
     * benar-benar tersimpan.
     */
    public function create(Request $request): View
    {
        $evidence = null;
        $previewError = null;

        if ($request->filled('work_date')) {
            $user = $request->user();

            if ($user !== null && $user->employee_id !== null) {
                $workDate = new DateTimeImmutable((string) $request->string('work_date'));
                $evidence = $this->attendance->overtimeEvidenceOn($user->employee_id, $workDate);

                if ($evidence === null) {
                    $previewError = NoOvertimeEvidence::forDate($workDate)->getMessage();
                }
            }
        }

        return view('overtime.create', [
            'evidence' => $evidence,
            'previewError' => $previewError,
        ]);
    }

    public function store(SubmitOvertimeRequestForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $spklNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                overtimeType: OvertimeType::from((string) $request->string('overtime_type')),
                workDate: new DateTimeImmutable((string) $request->string('work_date')),
                actor: new AuditActor(
                    // Audit trail memakai employee_id (uuid), sejalan dengan
                    // approver_id/initiator_id di seluruh skema — bukan id
                    // akun login (bigint), yang bertipe berbeda dari kolom
                    // actor_id (uuid) pada aud_change_logs.
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException|RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('ess.dashboard')
            ->with('sukses', "Pengajuan lembur {$spklNumber} berhasil dikirim dan menunggu persetujuan.");
    }

    /**
     * Surat Perintah Kerja Lembur — hanya tersedia setelah disetujui
     * (dokumen belum sah bila keputusan belum ada). Dapat diunduh oleh
     * pemohon sendiri, penyetuju yang memutuskannya, hr_admin dalam
     * lingkup kantornya sendiri, atau hr_approver/auditor bank-wide.
     */
    public function downloadSpkl(Request $request, string $id): Response
    {
        $row = DB::table('ovt_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->leftJoin('emp_employees as approver', 'approver.id', '=', 'r.approver_id')
            ->select(
                'r.id', 'r.spkl_number', 'r.overtime_type', 'r.work_date', 'r.payable_hours',
                'r.amount_cents', 'r.decided_at', 'r.status', 'r.approver_id',
                'r.disbursed_at', 'r.disbursement_reference',
                'e.id as employee_id', 'e.office_id', 'e.nrp', 'e.full_name', 'e.person_grade',
                'p.name as position_name', 'o.name as office_name',
                'approver.full_name as approver_name',
            )
            ->where('r.id', $id)
            ->first();

        abort_if($row === null, 404);
        // 'disbursed' TETAP boleh dicetak — dokumen tidak menghilang
        // begitu dibayar, cuma bertambah keterangan status lunas
        // (lihat spkl-pdf.blade.php).
        abort_unless(in_array($row->status, ['approved', 'disbursed'], true), 404, 'SPKL hanya tersedia untuk pengajuan yang sudah disetujui.');

        $isOwner = $row->employee_id === $this->actor->employeeId();
        $isApprover = $row->approver_id === $this->actor->employeeId();
        // String peran mentah ('hr_admin'/'hr_approver'/'auditor'), bukan
        // App\Modules\Access\Domain\Role — modul ini hanya boleh
        // bergantung pada Access\Contracts, tidak pada Domain-nya (M-1).
        $isHrAdminInOffice = $this->actor->hasRole('hr_admin') && $row->office_id === $this->actor->officeId();
        $isBankWideStaff = $this->actor->hasRole('hr_approver') || $this->actor->hasRole('auditor');

        abort_unless($isOwner || $isApprover || $isHrAdminInOffice || $isBankWideStaff, 403);

        $overtimeType = OvertimeType::from($row->overtime_type);

        $pdf = Pdf::loadView('overtime.spkl-pdf', [
            'row' => $row,
            'overtimeTypeLabel' => $overtimeType->label(),
        ]);

        // spkl_number mengandung "/" (mis. SPKL/2026/08/0001) — tidak
        // valid sebagai nama berkas unduhan (Content-Disposition).
        $fileName = str_replace('/', '-', $row->spkl_number);

        return $pdf->download("{$fileName}.pdf");
    }
}
