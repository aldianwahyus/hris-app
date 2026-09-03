<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\SubmitEmployeeProfileChange;
use App\Modules\Employee\Domain\EditableEmployeeField;
use App\Modules\Employee\Domain\InvalidProfileChange;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Data pegawai di kantor yang ditugaskan — lingkup OFFICE (ARCH-001
 * §6.2) untuk Admin SDM (maker).
 *
 * index() hanya-baca. edit()/update() adalah maker pada alur
 * maker-checker (TOR Fase I, Data Pegawai) — TIDAK langsung menulis
 * emp_employees, hanya mengajukan usulan yang menunggu hr_approver
 * (checker, lihat EmployeeApprovalQueueController).
 */
final class EmployeeDirectoryController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly SubmitEmployeeProfileChange $submit,
    ) {}

    public function index(): View
    {
        $officeId = $this->actor->officeId();

        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $office = DB::table('md_offices')->where('id', $officeId)->first();

        $employees = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->select(
                'e.id', 'e.nrp', 'e.full_name', 'e.employment_status',
                'e.join_date', 'e.person_grade', 'e.job_grade',
                'p.name as position_name'
            )
            ->where('e.office_id', $officeId)
            ->orderBy('e.full_name')
            ->get();

        return view('admin.employee-directory', compact('office', 'employees'));
    }

    public function edit(string $id): View
    {
        $employee = $this->employeeInOwnOffice($id);

        // Kantor/jabatan nonaktif tidak boleh dipilih untuk penugasan
        // BARU, TAPI nilai kantor/jabatan pegawai ini SENDIRI tetap
        // disertakan meski sudah nonaktif — supaya form tidak terlihat
        // rusak (nilai terpilih hilang dari opsi) untuk pegawai yang
        // kantor/jabatannya dinonaktifkan SETELAH dia ditempatkan di sana.
        // ->value() dipakai (bukan properti $employee->office_id) supaya
        // hasilnya bertipe string|null yang jelas, bukan properti dinamis
        // pada object generik dari DB::table()->first().
        $employeeOfficeId = DB::table('emp_employees')->where('id', $id)->value('office_id');
        $employeePositionId = DB::table('emp_employees')->where('id', $id)->value('position_id');

        $offices = DB::table('md_offices')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employeeOfficeId))
            ->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('md_positions')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employeePositionId))
            ->orderBy('name')->get(['id', 'name']);

        // Atasan Langsung untuk Struktur Organisasi dibatasi kantor
        // sendiri — sejalan lingkup hr_admin yang memang per kantor.
        // employeeInOwnOffice() sudah menjamin office_id pegawai ini
        // SAMA dengan kantor aktor — pakai actor->officeId() langsung
        // (bukan $employee->office_id) menghindari celah PHPStan pada
        // akses properti object bebas LINTAS method.
        $employeesForSupervisor = DB::table('emp_employees')
            ->where('office_id', $this->actor->officeId())
            ->where('id', '!=', $id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nrp']);

        $pendingRequests = DB::table('emp_profile_change_requests')
            ->where('employee_id', $id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $history = DB::table('aud_change_logs')
            ->where('auditable_type', 'employee_profile_change')
            ->where('auditable_id', $id)
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        $familyMembers = DB::table('emp_family_members')->where('employee_id', $id)->orderBy('created_at')->get();
        $internalWorkHistories = DB::table('emp_internal_work_histories')->where('employee_id', $id)->orderByDesc('start_date')->get();
        $externalWorkHistories = DB::table('emp_external_work_histories')->where('employee_id', $id)->orderByDesc('start_date')->get();
        $healthRecords = DB::table('emp_health_records')->where('employee_id', $id)->orderByDesc('record_date')->get();
        $contracts = DB::table('emp_contracts')->where('employee_id', $id)->orderByDesc('start_date')->get();

        // Riwayat LMS — read-only, bagian dari integrasi modul Pelatihan
        // ke Data Kepegawaian (lihat RecordCourseCompletion yang juga
        // menulis-turun ke emp_trainings/emp_certifications saat lulus).
        $lmsHistory = DB::table('lms_enrollments as en')
            ->join('lms_course_batches as b', 'b.id', '=', 'en.batch_id')
            ->join('lms_courses as c', 'c.id', '=', 'b.course_id')
            ->where('en.employee_id', $id)
            ->select(
                'en.status', 'en.completion_status', 'en.score', 'en.certificate_number',
                'c.title as course_title', 'b.batch_code', 'b.start_date', 'b.end_date',
            )
            ->orderByDesc('b.start_date')
            ->get();

        return view('admin.employee-edit', [
            'employee' => $employee,
            'offices' => $offices,
            'positions' => $positions,
            'employeesForSupervisor' => $employeesForSupervisor,
            'pendingRequests' => $pendingRequests,
            'history' => $history,
            'familyMembers' => $familyMembers,
            'internalWorkHistories' => $internalWorkHistories,
            'externalWorkHistories' => $externalWorkHistories,
            'healthRecords' => $healthRecords,
            'contracts' => $contracts,
            'lmsHistory' => $lmsHistory,
            'employeeId' => $id,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $employee = $this->employeeInOwnOffice($id);
        $actingEmployeeId = $this->actor->employeeId();

        abort_if($actingEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $proposedChanges = [];

        // Tunjangan Jabatan/Penyesuaian diinput Admin SDM dalam Rupiah utuh
        // (mis. "11100000") — dikonversi ke sen di sini sebelum dibandingkan/
        // disimpan, pola sama seperti SalaryScaleController.
        $moneyFields = [
            EditableEmployeeField::TunjanganJabatanCents->value,
            EditableEmployeeField::TunjanganPenyesuaianCents->value,
        ];
        $intFields = [
            EditableEmployeeField::PersonGrade->value,
            EditableEmployeeField::JobGrade->value,
            EditableEmployeeField::Tanggungan->value,
        ];

        foreach (EditableEmployeeField::values() as $field) {
            if (! $request->filled($field)) {
                continue;
            }

            $raw = $request->string($field)->toString();

            $value = match (true) {
                in_array($field, $moneyFields, true) => (int) round(((float) $raw) * 100),
                in_array($field, $intFields, true) => (int) $raw,
                default => $raw,
            };

            if ((string) ($employee->{$field} ?? '') === (string) $value) {
                continue; // tidak berubah — tidak perlu diajukan
            }

            $proposedChanges[$field] = $value;
        }

        try {
            $this->submit->handle(
                employeeId: $id,
                proposedChanges: $proposedChanges,
                requestedBy: $actingEmployeeId,
                actor: new AuditActor(
                    actorId: $actingEmployeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InvalidProfileChange|DomainException|RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('hr.employees.edit', $id)
            ->with('sukses', 'Pengajuan perubahan data pegawai terkirim, menunggu persetujuan hr_approver.');
    }

    private function employeeInOwnOffice(string $id): object
    {
        $officeId = $this->actor->officeId();

        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        $employee = DB::table('emp_employees')->where('id', $id)->first();

        abort_if($employee === null, 404);
        abort_unless($employee->office_id === $officeId, 403, 'Pegawai ini di luar lingkup kantor Anda.');

        return $employee;
    }
}
