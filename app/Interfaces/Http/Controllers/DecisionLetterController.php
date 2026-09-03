<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\GenerateSalaryChangeTemplate;
use App\Modules\Employee\Application\ImportSalaryChangeDecisionLetters;
use App\Modules\Employee\Application\RecordDecisionLetter;
use App\Modules\Employee\Application\RemoveDecisionLetter;
use App\Modules\Employee\Application\ResolveEmployeeForHrAction;
use App\Modules\Employee\Domain\SkType;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ValueError;

/**
 * Surat Keputusan (SK) — modul TERSENDIRI (BUKAN bagian dari Data
 * Pegawai) untuk SYSADMIN/hr_admin. Tulis LANGSUNG tanpa persetujuan
 * (pola sama EmployeeSanctionController lama, lewat
 * ResolveEmployeeForHrAction), BISA untuk satu pegawai ATAU banyak
 * sekaligus (massal — satu form, banyak employee_ids tercentang, satu
 * dokumen SK yang sama diunggah SEKALI untuk semuanya).
 *
 * SK Mutasi/Promosi MEMICU pengajuan perubahan data induk lewat
 * SubmitEmployeeProfileChange yang SUDAH ADA (RecordDecisionLetter yang
 * mengurusnya, DIPANGGIL PER PEGAWAI dalam loop) — pengajuan itu
 * SENDIRI tetap menunggu persetujuan hr_approver seperti biasa, TIDAK
 * berubah dari sebelumnya.
 */
final class DecisionLetterController extends Controller
{
    public function __construct(
        private readonly ResolveEmployeeForHrAction $resolveEmployee,
        private readonly CurrentActor $actor,
        private readonly RecordDecisionLetter $record,
        private readonly RemoveDecisionLetter $remove,
        private readonly GenerateSalaryChangeTemplate $generateSalaryChangeTemplate,
        private readonly ImportSalaryChangeDecisionLetters $importSalaryChanges,
    ) {}

    public function index(): View
    {
        $bankWide = $this->isBankWide();

        $query = DB::table('emp_decision_letters as d')
            ->join('emp_employees as e', 'e.id', '=', 'd.employee_id')
            ->leftJoin('emp_profile_change_requests as r', 'r.id', '=', 'd.profile_change_request_id')
            ->select('d.*', 'e.full_name', 'e.nrp', 'r.status as perubahan_status');

        if ($bankWide) {
            $query->join('md_offices as o', 'o.id', '=', 'e.office_id')->addSelect('o.name as office_name');
        } else {
            $officeId = $this->actor->officeId();
            abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');
            $query->where('e.office_id', $officeId);
        }

        $decisionLetters = $query->orderByDesc('d.sk_date')->orderBy('d.sk_number')->get();

        // Tanda Tangan Elektronik — dipetakan per SK (satu SK bisa
        // ditandatangani lebih dari satu pejabat, tampilkan yang PALING
        // BARU saja di daftar ringkas ini).
        $signatures = DB::table('sig_signatures')
            ->where('signable_type', 'decision_letter')
            ->whereIn('signable_id', $decisionLetters->pluck('id'))
            ->orderByDesc('signed_at')
            ->get()
            ->keyBy('signable_id');

        return view('admin.decision-letter-index', compact('decisionLetters', 'bankWide', 'signatures'));
    }

    public function create(): View
    {
        $bankWide = $this->isBankWide();

        $employeesQuery = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->select('e.id', 'e.nrp', 'e.full_name', 'p.name as position_name');

        if ($bankWide) {
            $employeesQuery->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->addSelect('o.name as office_name')
                ->orderBy('o.name');
        } else {
            $officeId = $this->actor->officeId();
            abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');
            $employeesQuery->where('e.office_id', $officeId);
        }

        $employees = $employeesQuery->orderBy('e.full_name')->get();
        $offices = DB::table('md_offices')->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('md_positions')->orderBy('name')->get(['id', 'name']);

        return view('admin.decision-letter-create', compact('employees', 'offices', 'positions', 'bankWide'));
    }

    /**
     * Layar KHUSUS SK Perubahan Gaji — TERPISAH dari create()/store()
     * generik karena gaji SAAT INI berbeda-beda per pegawai, jadi tidak
     * bisa memakai pola "satu set target diterapkan sama ke semua
     * pegawai tercentang" yang benar untuk Mutasi/Promosi. Menampilkan
     * gaji saat ini per pegawai di tabel, form mengirim perubahan
     * PER PEGAWAI lewat storeSalaryChange().
     */
    public function createSalaryChange(): View
    {
        $bankWide = $this->isBankWide();

        $employeesQuery = DB::table('emp_employees as e')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->select(
                'e.id', 'e.nrp', 'e.full_name', 'p.name as position_name',
                'e.person_grade', 'e.salary_step', 'e.tunjangan_jabatan_cents', 'e.tunjangan_penyesuaian_cents',
            );

        if ($bankWide) {
            $employeesQuery->join('md_offices as o', 'o.id', '=', 'e.office_id')
                ->addSelect('o.name as office_name')
                ->orderBy('o.name');
        } else {
            $officeId = $this->actor->officeId();
            abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');
            $employeesQuery->where('e.office_id', $officeId);
        }

        $employees = $employeesQuery->orderBy('e.full_name')->get();

        return view('admin.decision-letter-salary-change', compact('employees', 'bankWide'));
    }

    public function storeSalaryChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sk_number' => ['required', 'string', 'max:100'],
            'sk_date' => ['required', 'date'],
            'effective_date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'changes' => ['required', 'array'],
            'changes.*' => ['array'],
            'changes.*.person_grade' => ['nullable', 'integer'],
            'changes.*.salary_step' => ['nullable', 'integer', 'min:1'],
            'changes.*.tunjangan_jabatan' => ['nullable', 'integer', 'min:0'],
            'changes.*.tunjangan_penyesuaian' => ['nullable', 'integer', 'min:0'],
        ]);

        // Baris pegawai yang keempat sub-field-nya kosong dianggap "tidak
        // berubah" — dibuang di sini SEBELUM validasi lingkup/penyimpanan,
        // supaya tidak ikut divalidasi/dianggap gagal.
        $rowsWithChanges = array_filter(
            $validated['changes'],
            fn (array $row) => array_filter($row, fn ($v) => $v !== null) !== []
        );

        if ($rowsWithChanges === []) {
            return back()->withInput()->with('gagal', 'Tidak ada perubahan gaji yang diisi untuk pegawai mana pun.');
        }

        $employeeIds = array_keys($rowsWithChanges);

        // Lingkup DIVALIDASI DULU untuk SELURUH baris berisi (pola sama
        // store() generik) — gagal total tanpa satu pun tersimpan, bukan
        // gagal separuh jalan.
        foreach ($employeeIds as $employeeId) {
            $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());
        }

        $documentPath = null;
        $documentOriginalName = null;

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $stored = $file->store('sk/massal', 's3');

            abort_if($stored === false, 500, 'Gagal mengunggah berkas SK — coba lagi.');

            $documentPath = $stored;
            $documentOriginalName = $file->getClientOriginalName();
        }

        $skDate = new DateTimeImmutable($validated['sk_date']);
        $effectiveDate = isset($validated['effective_date']) ? new DateTimeImmutable($validated['effective_date']) : null;
        $actor = $this->currentAuditActor($request);
        $requestedBy = (string) $this->actor->employeeId();

        $saved = 0;
        $failed = [];

        foreach ($rowsWithChanges as $employeeId => $row) {
            $proposedChanges = array_filter([
                'person_grade' => $row['person_grade'] ?? null,
                'salary_step' => $row['salary_step'] ?? null,
                'tunjangan_jabatan_cents' => isset($row['tunjangan_jabatan']) ? ((int) $row['tunjangan_jabatan']) * 100 : null,
                'tunjangan_penyesuaian_cents' => isset($row['tunjangan_penyesuaian']) ? ((int) $row['tunjangan_penyesuaian']) * 100 : null,
            ], fn ($v) => $v !== null);

            try {
                $this->record->handle(
                    employeeId: $employeeId,
                    skType: SkType::PerubahanGaji,
                    skNumber: $validated['sk_number'],
                    skDate: $skDate,
                    effectiveDate: $effectiveDate,
                    description: $validated['description'],
                    documentPath: $documentPath,
                    documentOriginalName: $documentOriginalName,
                    proposedChanges: $proposedChanges,
                    requestedBy: $requestedBy,
                    actor: $actor,
                );

                $saved++;
            } catch (DomainException|RuntimeException $e) {
                $nama = DB::table('emp_employees')->where('id', $employeeId)->value('full_name') ?? $employeeId;
                $failed[] = "{$nama}: {$e->getMessage()}";
            }
        }

        $total = count($rowsWithChanges);
        $pesan = "SK Perubahan Gaji tersimpan untuk {$saved} dari {$total} pegawai yang diisi perubahannya.";

        if ($failed !== []) {
            $pesan .= ' Gagal: '.implode('; ', array_slice($failed, 0, 5));
        }

        if ($saved > 0) {
            $pesan .= ' Pengajuan perubahan data induk menunggu persetujuan hr_approver.';
        }

        return redirect()->route('sk.index')->with($saved > 0 ? 'sukses' : 'gagal', $pesan);
    }

    public function salaryChangeTemplate(): Response
    {
        $csv = $this->generateSalaryChangeTemplate->handle($this->actor->roles(), $this->actor->officeId());

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template-perubahan-gaji.csv"',
        ]);
    }

    public function importSalaryChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sk_number' => ['required', 'string', 'max:100'],
            'sk_date' => ['required', 'date'],
            'effective_date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $documentPath = null;
        $documentOriginalName = null;

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $stored = $file->store('sk/massal', 's3');

            abort_if($stored === false, 500, 'Gagal mengunggah berkas SK — coba lagi.');

            $documentPath = $stored;
            $documentOriginalName = $file->getClientOriginalName();
        }

        $realPath = $request->file('berkas')->getRealPath();

        abort_if($realPath === false || $realPath === null, 422, 'Berkas gagal diunggah.');

        try {
            $result = $this->importSalaryChanges->handle(
                filePath: $realPath,
                skNumber: $validated['sk_number'],
                skDate: new DateTimeImmutable($validated['sk_date']),
                effectiveDate: isset($validated['effective_date']) ? new DateTimeImmutable($validated['effective_date']) : null,
                description: $validated['description'],
                documentPath: $documentPath,
                documentOriginalName: $documentOriginalName,
                actorRoles: $this->actor->roles(),
                actorOfficeId: $this->actor->officeId(),
                requestedBy: (string) $this->actor->employeeId(),
                actor: $this->currentAuditActor($request),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('sk.salary-change.create')->with('gagal', $e->getMessage());
        }

        $pesan = "Impor selesai: {$result->imported} pegawai diproses, {$result->skippedNoChange} tanpa perubahan, {$result->skipped} baris gagal.";

        if ($result->skippedReasons !== []) {
            $pesan .= ' Rincian gagal: '.implode(' ', array_slice($result->skippedReasons, 0, 5));
        }

        return redirect()->route('sk.index')->with($result->imported > 0 ? 'sukses' : 'gagal', $pesan);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['uuid', 'exists:emp_employees,id'],
            'sk_type' => ['required', 'string', 'in:mutasi,promosi,sanksi,lainnya'],
            'sk_number' => ['required', 'string', 'max:100'],
            'sk_date' => ['required', 'date'],
            'effective_date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'target_office_id' => ['required_if:sk_type,mutasi', 'nullable', 'uuid', 'exists:md_offices,id'],
            'target_position_id' => ['required_if:sk_type,promosi', 'nullable', 'uuid', 'exists:md_positions,id'],
            'target_person_grade' => ['nullable', 'integer'],
            'target_job_grade' => ['nullable', 'integer'],
        ]);

        try {
            $skType = SkType::from($validated['sk_type']);
        } catch (ValueError) {
            return back()->withInput()->with('gagal', 'Jenis SK tidak dikenali.');
        }

        $documentPath = null;
        $documentOriginalName = null;

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            // Satu berkas fisik, diunggah SEKALI — path yang sama dipakai
            // ulang untuk setiap baris pegawai di bawah (bukan diunggah
            // ulang per pegawai).
            $stored = $file->store('sk/massal', 's3');

            abort_if($stored === false, 500, 'Gagal mengunggah berkas SK — coba lagi.');

            $documentPath = $stored;
            $documentOriginalName = $file->getClientOriginalName();
        }

        $effectiveDate = isset($validated['effective_date']) ? new DateTimeImmutable($validated['effective_date']) : null;
        $skDate = new DateTimeImmutable($validated['sk_date']);
        $proposedChangesTemplate = $this->proposedChangesFor($skType, $validated, $effectiveDate);

        $actor = $this->currentAuditActor($request);
        $requestedBy = (string) $this->actor->employeeId();

        // Lingkup DIVALIDASI DULU untuk SELURUH daftar (bukan di dalam loop
        // penyimpanan) — resolveEmployee->handle() memakai abort_unless()
        // (403 langsung, BUKAN exception yang bisa ditangkap per-pegawai),
        // jadi kalau dicek belakangan sambil menyimpan, satu pegawai di
        // luar lingkup bisa menghentikan request SETELAH pegawai
        // sebelumnya sudah tersimpan (transaksinya sendiri-sendiri per
        // pegawai) — pengguna melihat 403 padahal sebagian sudah tersimpan
        // diam-diam. Validasi lingkup di sini murni baca, jadi aman
        // dilakukan lebih dulu: gagal total tanpa satu pun tersimpan,
        // bukan gagal separuh jalan.
        foreach ($validated['employee_ids'] as $employeeId) {
            $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());
        }

        $saved = 0;
        $failed = [];

        foreach ($validated['employee_ids'] as $employeeId) {
            try {
                $this->record->handle(
                    employeeId: $employeeId,
                    skType: $skType,
                    skNumber: $validated['sk_number'],
                    skDate: $skDate,
                    effectiveDate: $effectiveDate,
                    description: $validated['description'],
                    documentPath: $documentPath,
                    documentOriginalName: $documentOriginalName,
                    proposedChanges: $proposedChangesTemplate,
                    requestedBy: $requestedBy,
                    actor: $actor,
                );

                $saved++;
            } catch (DomainException|RuntimeException $e) {
                $nama = DB::table('emp_employees')->where('id', $employeeId)->value('full_name') ?? $employeeId;
                $failed[] = "{$nama}: {$e->getMessage()}";
            }
        }

        $total = count($validated['employee_ids']);
        $pesan = "SK tersimpan untuk {$saved} dari {$total} pegawai.";

        if ($failed !== []) {
            $pesan .= ' Gagal: '.implode('; ', array_slice($failed, 0, 5));
        }

        if ($proposedChangesTemplate !== null && $saved > 0) {
            $pesan .= ' Pengajuan perubahan data induk menunggu persetujuan hr_approver.';
        }

        return redirect()->route('sk.index')->with($saved > 0 ? 'sukses' : 'gagal', $pesan);
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $row = DB::table('emp_decision_letters')->where('id', $id)->first();

        abort_if($row === null, 404);

        $this->resolveEmployee->handle($row->employee_id, $this->actor->roles(), $this->actor->officeId());

        $this->remove->handle($id, $this->currentAuditActor($request));

        return back()->with('sukses', 'SK dihapus.');
    }

    public function download(string $id): StreamedResponse
    {
        $row = DB::table('emp_decision_letters')->where('id', $id)->first();

        abort_if($row === null, 404);

        $this->resolveEmployee->handle($row->employee_id, $this->actor->roles(), $this->actor->officeId());

        abort_if($row->document_path === null, 404);

        return Storage::disk('s3')->download($row->document_path, $row->document_original_name);
    }

    private function isBankWide(): bool
    {
        return $this->actor->hasRole('hr_approver') || $this->actor->hasRole('system_admin');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    private function proposedChangesFor(SkType $skType, array $validated, ?DateTimeImmutable $effectiveDate): ?array
    {
        return match ($skType) {
            SkType::Mutasi => array_filter([
                'office_id' => $validated['target_office_id'],
                'tmt_pangkat' => $effectiveDate?->format('Y-m-d'),
            ], fn ($v) => $v !== null),
            SkType::Promosi => array_filter([
                'position_id' => $validated['target_position_id'] ?? null,
                'person_grade' => $validated['target_person_grade'] ?? null,
                'job_grade' => $validated['target_job_grade'] ?? null,
                'tmt_pangkat' => $effectiveDate?->format('Y-m-d'),
            ], fn ($v) => $v !== null),
            SkType::Sanksi, SkType::PerubahanGaji, SkType::Lainnya => null,
        };
    }

    private function currentAuditActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
