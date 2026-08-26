<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\SubmitEmployeeProfileChange;
use App\Modules\Employee\Application\SubmitNewEmployeeRequest;
use App\Modules\Employee\Domain\EditableEmployeeField;
use App\Modules\Employee\Domain\InvalidProfileChange;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * SYSADMIN sebagai maker KEDUA untuk data pegawai (lingkup BANK_WIDE,
 * bukan OFFICE seperti hr_admin) — menambah satu jalur usulan, BUKAN
 * membongkar §6.3: usulan tetap menunggu hr_approver (checker) yang
 * SAMA persis dengan usulan hr_admin lewat EmployeeDirectoryController
 * (emp_profile_change_requests tidak membedakan siapa maker-nya).
 *
 * `SubmitEmployeeProfileChange` dipakai APA ADANYA (sudah scope-agnostic,
 * tidak ada pengecekan kantor di dalamnya) — hanya gerbang OFFICE milik
 * `EmployeeDirectoryController::employeeInOwnOffice()` yang SENGAJA
 * tidak direplikasi di sini (SYSADMIN memang boleh mengusulkan untuk
 * pegawai kantor mana pun).
 */
final class SystemAdminEmployeeController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly SubmitEmployeeProfileChange $submit,
        private readonly SubmitNewEmployeeRequest $submitNew,
    ) {}

    public function index(): View
    {
        $employees = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->select(
                'e.id', 'e.nrp', 'e.full_name', 'e.employment_status',
                'e.join_date', 'e.person_grade', 'e.job_grade',
                'p.name as position_name', 'o.name as office_name'
            )
            ->orderBy('o.name')
            ->orderBy('e.full_name')
            ->get();

        return view('admin.sysadmin-employee-index', compact('employees'));
    }

    public function edit(string $id): View
    {
        $employee = $this->existingEmployee($id);

        // Pola sama EmployeeDirectoryController::edit() — kantor/jabatan
        // nonaktif tidak boleh dipilih untuk penugasan baru, TAPI nilai
        // milik pegawai ini sendiri tetap disertakan meski sudah nonaktif.
        // ->value() (bukan properti $employee->office_id) — lihat catatan
        // sama di EmployeeDirectoryController::edit().
        $employeeOfficeId = DB::table('emp_employees')->where('id', $id)->value('office_id');
        $employeePositionId = DB::table('emp_employees')->where('id', $id)->value('position_id');

        $offices = DB::table('md_offices')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employeeOfficeId))
            ->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('md_positions')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employeePositionId))
            ->orderBy('name')->get(['id', 'name']);

        // SYSADMIN bank-wide — Atasan Langsung untuk Struktur Organisasi
        // boleh dipilih dari seluruh pegawai, bukan hanya kantor sendiri.
        $employeesForSupervisor = DB::table('emp_employees')
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

        return view('admin.sysadmin-employee-edit', [
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
            'employeeId' => $id,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $employee = $this->existingEmployee($id);
        $actingEmployeeId = $this->actor->employeeId();

        abort_if($actingEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $proposedChanges = [];

        // Pola konversi sama persis EmployeeDirectoryController::update()
        // — dijaga identik dengan sengaja supaya perilaku dua maker ini
        // tidak diam-diam berbeda.
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
                continue;
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

        return redirect()->route('sysadmin.employees.edit', $id)
            ->with('sukses', 'Pengajuan perubahan data pegawai terkirim, menunggu persetujuan hr_approver.');
    }

    public function create(): View
    {
        // Kantor/jabatan yang nonaktif tidak boleh dipilih untuk pegawai
        // BARU (lihat OfficeController/PositionController §is_active) —
        // beda dari edit() pegawai yang SUDAH ADA, yang tidak perlu
        // pembatasan ini.
        $offices = DB::table('md_offices')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('md_positions')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // SYSADMIN bank-wide — Atasan Langsung boleh dipilih dari seluruh
        // pegawai yang sudah ada (pola sama create() Struktur Organisasi
        // di edit()), bukan cuma kantor tujuan pegawai baru ini.
        $employeesForSupervisor = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp']);

        return view('admin.sysadmin-employee-create', compact('offices', 'positions', 'employeesForSupervisor'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nrp' => ['required', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:150'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:L,P'],
            'email' => ['nullable', 'email', 'max:150'],
            'join_date' => ['required', 'date'],
            'employment_status' => ['required', 'string', 'in:tetap,trainee,kontrak,outsource'],
            'office_id' => ['required', 'uuid', 'exists:md_offices,id'],
            'position_id' => ['required', 'uuid', 'exists:md_positions,id'],
            'person_grade' => ['nullable', 'integer', 'min:1', 'max:255'],
            'job_grade' => ['nullable', 'integer', 'min:1', 'max:255'],
            // Data kepegawaian tambahan — pola/validasi SAMA persis
            // dengan _employee-edit-form.blade.php (EmployeeDirectoryController/
            // SystemAdminEmployeeController::update()), supaya field yang
            // sama tidak divalidasi beda antara "Tambah" dan "Ubah".
            'marital_status' => ['nullable', 'string', 'in:belum menikah,menikah'],
            'tanggungan' => ['nullable', 'integer', 'min:0', 'max:3'],
            'permanent_date' => ['nullable', 'date'],
            'supervisor_id' => ['nullable', 'uuid', 'exists:emp_employees,id'],
            'division' => ['nullable', 'string', 'max:100'],
            'agama' => ['nullable', 'string', 'in:Islam,Kristen Protestan,Kristen Katolik,Hindu,Buddha,Konghucu'],
            'nomor_ktp' => ['nullable', 'string', 'max:20'],
            'nomor_npwp' => ['nullable', 'string', 'max:25'],
            'bpjs_tenaga_kerja' => ['nullable', 'string', 'max:30'],
            'bpjs_kesehatan' => ['nullable', 'string', 'max:30'],
            'nomor_simpeda' => ['nullable', 'string', 'max:30'],
            'nomor_tambora_rencana' => ['nullable', 'string', 'max:30'],
            'tmt_pangkat' => ['nullable', 'date'],
            // Data pribadi — pola/validasi SAMA persis EmployeeCvController::update()
            // (biasanya diisi pegawai sendiri lewat CV Saya, tapi SYSADMIN
            // boleh melengkapinya sekaligus saat membuat data awal).
            'alamat' => ['nullable', 'string', 'max:2000'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat_telepon' => ['nullable', 'string', 'max:20'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:30'],
            'pendidikan_jurusan' => ['nullable', 'string', 'max:100'],
        ]);

        // Aturan SAMA ProfileChangeValidator: status tetap wajib punya
        // tanggal jadi pegawai tetap — belum berlaku otomatis di jalur
        // pegawai baru sebelum ini (jalur terpisah dari perubahan profil),
        // ditegakkan manual di sini supaya data baru tidak lolos tanpa itu.
        if (($validated['employment_status'] ?? null) === 'tetap' && ($validated['permanent_date'] ?? null) === null) {
            return back()->withInput()->with('gagal', 'Status kepegawaian "tetap" wajib mengisi Tanggal Jadi Pegawai Tetap.');
        }

        $actingEmployeeId = $this->actor->employeeId();
        abort_if($actingEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->submitNew->handle(
                proposedData: $validated,
                requestedBy: $actingEmployeeId,
                actor: new AuditActor(
                    actorId: $actingEmployeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('sysadmin.employees.index')
            ->with('sukses', 'Pengajuan pegawai baru terkirim, menunggu persetujuan hr_approver.');
    }

    private function existingEmployee(string $id): object
    {
        $employee = DB::table('emp_employees')->where('id', $id)->first();

        abort_if($employee === null, 404);

        return $employee;
    }
}
