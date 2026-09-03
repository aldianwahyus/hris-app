<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manajemen Aset — katalog aset perusahaan. hr_admin melihat kantornya
 * sendiri (lingkup OFFICE), hr_approver/system_admin melihat seluruh
 * bank (lingkup BANK_WIDE) — pola SAMA PERSIS
 * OvertimeRecapController::scopedRows(). Status 'dipakai' HANYA boleh
 * berubah lewat AssignAsset/ReturnAsset (lihat AssetAssignmentController)
 * — TIDAK ADA di pilihan status pada form ubah di sini, karena
 * penugasan wajib tercatat sebagai baris ast_assignments, bukan
 * sekadar label status.
 */
final class AssetController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $assets = DB::table('ast_assets as a')
            ->join('md_offices as o', 'o.id', '=', 'a.office_id')
            ->leftJoin('ast_assignments as asg', function ($join) {
                $join->on('asg.asset_id', '=', 'a.id')->whereNull('asg.returned_at');
            })
            ->leftJoin('emp_employees as e', 'e.id', '=', 'asg.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('a.office_id', $officeId))
            ->select(
                'a.id', 'a.asset_code', 'a.name', 'a.category', 'a.brand_model', 'a.serial_number',
                'a.purchase_date', 'a.purchase_value_cents', 'a.condition', 'a.status', 'a.notes',
                'a.office_id', 'o.name as office_name',
                'asg.id as assignment_id', 'e.full_name as holder_name', 'e.nrp as holder_nrp',
            )
            ->orderBy('a.name')
            ->get();

        $offices = DB::table('md_offices')
            ->when($officeId !== null, fn ($q) => $q->where('id', $officeId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $employees = DB::table('emp_employees')
            ->when($officeId !== null, fn ($q) => $q->where('office_id', $officeId))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nrp', 'office_id']);

        return view('admin.asset-directory', compact('assets', 'offices', 'employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:50'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_value_cents' => ['nullable', 'integer', 'min:0'],
            'office_id' => ['required', 'uuid', 'exists:md_offices,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $codeTaken = DB::table('ast_assets')->where('asset_code', $validated['asset_code'])->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode aset itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('ast_assets')->insert([
            'id' => $id,
            'asset_code' => $validated['asset_code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'brand_model' => $validated['brand_model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_value_cents' => $validated['purchase_value_cents'] ?? null,
            'condition' => 'baik',
            'status' => 'tersedia',
            'office_id' => $validated['office_id'],
            'notes' => $validated['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'ast_asset',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.assets.index')->with('sukses', 'Aset tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $asset = DB::table('ast_assets')->where('id', $id)->first();

        abort_if($asset === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:50'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_value_cents' => ['nullable', 'integer', 'min:0'],
            'office_id' => ['required', 'uuid', 'exists:md_offices,id'],
            'condition' => ['required', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            // 'dipakai' tetap diterima DI SINI supaya baris tabel yang
            // sedang dipakai bisa menyunting kolom lain (nama, kategori,
            // dst.) tanpa memutus statusnya — tapi TIDAK PERNAH boleh
            // berubah MENJADI/DARI 'dipakai' lewat form ini (dua guard di
            // bawah), status itu HANYA boleh berubah lewat
            // AssignAsset/ReturnAsset.
            'status' => ['required', 'string', 'in:tersedia,perbaikan,dihapuskan,dipakai'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'dipakai' && $asset->status !== 'dipakai') {
            return back()->withInput()->with('gagal', 'Status "dipakai" hanya bisa diisi lewat penugasan aset, bukan diubah langsung.');
        }

        if ($asset->status === 'dipakai' && $validated['status'] !== 'dipakai') {
            return back()->withInput()->with('gagal', 'Aset ini sedang ditugaskan ke pegawai — kembalikan dulu sebelum mengubah statusnya di sini.');
        }

        DB::table('ast_assets')->where('id', $id)->update([
            ...$validated,
            'updated_at' => new DateTimeImmutable,
            'version' => $asset->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'ast_asset',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $asset,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.assets.index')->with('sukses', "Aset {$asset->name} diperbarui.");
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
