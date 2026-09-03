<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\ResolveEmployeeForHrAction;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manajemen Kontrak (pegawai kontrak/outsource) — pola PERSIS
 * EmployeeFamilyMemberController: HR-only (lingkup ditegakkan
 * ResolveEmployeeForHrAction), tulis LANGSUNG tanpa maker-checker
 * (administratif, bukan keputusan bisnis harian seperti Cuti/Lembur).
 */
final class EmployeeContractController extends Controller
{
    public function __construct(
        private readonly ResolveEmployeeForHrAction $resolveEmployee,
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function store(string $employeeId, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $validated = $request->validate([
            'contract_number' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'contract_type' => ['required', 'string', Rule::in(['kontrak', 'outsource'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        DB::table('emp_contracts')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'contract_number' => $validated['contract_number'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'contract_type' => $validated['contract_type'],
            'status' => 'aktif',
            'renewed_from_contract_id' => null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $actorEmployeeId,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'emp_contract',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return back()->with('sukses', 'Kontrak tersimpan.');
    }

    /**
     * Perpanjang — kontrak LAMA jadi status='diperpanjang' (bukan
     * dihapus/diedit), baris BARU dibuat dengan `renewed_from_contract_id`
     * merujuk yang lama, supaya rantai perpanjangan tetap terbaca utuh.
     */
    public function renew(string $employeeId, string $contractId, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $old = DB::table('emp_contracts')->where('id', $contractId)->where('employee_id', $employeeId)->first();

        abort_if($old === null, 404);

        $validated = $request->validate([
            'contract_number' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $newId = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::transaction(function () use ($old, $newId, $employeeId, $validated, $actorEmployeeId, $now) {
            DB::table('emp_contracts')->where('id', $old->id)->update([
                'status' => 'diperpanjang',
                'updated_at' => $now,
                'version' => $old->version + 1,
            ]);

            DB::table('emp_contracts')->insert([
                'id' => $newId,
                'employee_id' => $employeeId,
                'contract_number' => $validated['contract_number'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'contract_type' => $old->contract_type,
                'status' => 'aktif',
                'renewed_from_contract_id' => $old->id,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $actorEmployeeId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);
        });

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'emp_contract',
            auditableId: $newId,
            action: AuditAction::Created,
            newValues: [...$validated, 'renewed_from_contract_id' => $old->id],
        ));

        return back()->with('sukses', 'Kontrak diperpanjang.');
    }

    /** Tandai kontrak berakhir/diputus TANPA perpanjangan (mis. pegawai keluar). */
    public function updateStatus(string $employeeId, string $contractId, Request $request): RedirectResponse
    {
        $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());

        $contract = DB::table('emp_contracts')->where('id', $contractId)->where('employee_id', $employeeId)->first();

        abort_if($contract === null, 404);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['berakhir', 'diputus'])],
        ]);

        DB::table('emp_contracts')->where('id', $contractId)->update([
            'status' => $validated['status'],
            'updated_at' => new DateTimeImmutable,
            'version' => $contract->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'emp_contract',
            auditableId: $contractId,
            action: AuditAction::Updated,
            oldValues: ['status' => $contract->status],
            newValues: $validated,
        ));

        return back()->with('sukses', 'Status kontrak diperbarui.');
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
