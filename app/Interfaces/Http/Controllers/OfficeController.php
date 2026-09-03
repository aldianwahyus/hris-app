<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
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
 * Daftar Kantor — rujukan tunggal `md_offices` dipakai Data Pegawai, SK,
 * Payroll, dst (semua `DB::table('md_offices')` langsung, tidak ada
 * daftar lain yang perlu disinkronkan). SYSADMIN/Admin HC, bank-wide —
 * sama kategori dengan Formasi Kantor (data organisasi), BUKAN
 * konfigurasi teknis murni seperti geofence.
 *
 * Kantor TIDAK BISA DIHAPUS, hanya dinonaktifkan (`is_active`) — kolom
 * `deleted_at` yang sudah ada di skema SENGAJA tidak dipakai untuk ini,
 * lihat docblock migrasi 2026_08_30_000001. Titik ordinat (geofence)
 * TETAP dikelola terpisah di OfficeGeofenceController (tanggung jawab
 * berbeda, tidak diduplikasi di sini).
 */
final class OfficeController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $offices = DB::table('md_offices as o')
            ->leftJoin('md_offices as p', 'p.id', '=', 'o.parent_office_id')
            ->select('o.*', 'p.name as parent_office_name')
            ->orderBy('o.office_type')
            ->orderBy('o.name')
            ->get();

        $activeOffices = $offices->where('is_active', true)->values();

        return view('admin.office-directory', compact('offices', 'activeOffices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'office_type' => ['required', 'string', 'in:head_office,branch,sub_branch,functional'],
            'office_class' => ['nullable', 'string', 'max:30'],
            'parent_office_id' => ['nullable', 'uuid', 'exists:md_offices,id'],
            'timezone' => ['required', 'string', 'max:40'],
        ]);

        $codeTaken = DB::table('md_offices')->where('code', $validated['code'])->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode kantor itu sudah dipakai.');
        }

        if (! empty($validated['parent_office_id'])) {
            $parentActive = DB::table('md_offices')->where('id', $validated['parent_office_id'])->where('is_active', true)->exists();

            if (! $parentActive) {
                return back()->withInput()->with('gagal', 'Kantor induk yang dipilih tidak aktif.');
            }
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('md_offices')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'office_type' => $validated['office_type'],
            'office_class' => $validated['office_class'] ?? null,
            'parent_office_id' => $validated['parent_office_id'] ?? null,
            'timezone' => $validated['timezone'],
            'geofence_radius_m' => 100,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'md_office',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.offices.index')->with('sukses', 'Kantor tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $office = DB::table('md_offices')->where('id', $id)->first();

        abort_if($office === null, 404);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'office_type' => ['required', 'string', 'in:head_office,branch,sub_branch,functional'],
            'office_class' => ['nullable', 'string', 'max:30'],
            'parent_office_id' => ['nullable', 'uuid', 'exists:md_offices,id'],
            'timezone' => ['required', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (($validated['parent_office_id'] ?? null) === $id) {
            return back()->withInput()->with('gagal', 'Kantor tidak dapat menjadi induk dirinya sendiri.');
        }

        if (! empty($validated['parent_office_id'])) {
            $parentActive = DB::table('md_offices')->where('id', $validated['parent_office_id'])->where('is_active', true)->exists();

            if (! $parentActive) {
                return back()->withInput()->with('gagal', 'Kantor induk yang dipilih tidak aktif.');
            }
        }

        // Kode dipakai sebagai kunci pencarian saat impor CSV pegawai
        // baru (lihat ImportNewEmployeeRequests) — BUKAN foreign key
        // langsung (yang sudah terhubung tetap terhubung lewat office_id
        // UUID, tidak terpengaruh), jadi aman diubah selama tetap unik.
        $codeTakenByOther = DB::table('md_offices')->where('code', $validated['code'])->where('id', '!=', $id)->exists();

        if ($codeTakenByOther) {
            return back()->withInput()->with('gagal', 'Kode kantor itu sudah dipakai kantor lain.');
        }

        DB::table('md_offices')->where('id', $id)->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'office_type' => $validated['office_type'],
            'office_class' => $validated['office_class'] ?? null,
            'parent_office_id' => $validated['parent_office_id'] ?? null,
            'timezone' => $validated['timezone'],
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $office->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'md_office',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $office,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.offices.index')->with('sukses', "Kantor {$office->name} diperbarui.");
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
