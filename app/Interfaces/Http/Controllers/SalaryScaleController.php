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
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Skala Imbalan Kerja (Lampiran II, BPP/137/03/64/2026) — lingkup
 * TEKNIS Admin Sistem, sama seperti Konfigurasi Parameter Sistem
 * (SystemParameterController), tapi matriks (Person Grade × baris)
 * bukan skalar tunggal, sehingga tabelnya (pay_salary_scale) dan
 * kunci komposit (person_grade, step) berbeda.
 *
 * Nilai lama TIDAK PERNAH ditimpa — ditutup lalu digantikan nilai
 * baru, sama seperti pola Konfigurasi Parameter Sistem.
 */
final class SalaryScaleController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $auditRepository,
    ) {}

    public function index(): View
    {
        $today = now()->toDateString();

        $rows = DB::table('pay_salary_scale')
            ->whereNull('deleted_at')
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->orderBy('person_grade')
            ->orderBy('step')
            ->get();

        return view('admin.salary-scale', compact('rows'));
    }

    public function addValue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_grade' => ['required', 'integer', 'between:1,19'],
            'step' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'source_document' => ['nullable', 'string', 'max:150'],
        ]);

        $amountCents = (int) round(((float) $validated['amount']) * 100);
        $newRowId = (string) Uuid7::generate();

        try {
            DB::transaction(function () use ($validated, $amountCents, $newRowId) {
                $now = new DateTimeImmutable;

                $current = DB::table('pay_salary_scale')
                    ->where('person_grade', $validated['person_grade'])
                    ->where('step', $validated['step'])
                    ->whereNull('effective_to')
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($current !== null) {
                    $closeAt = (new DateTimeImmutable($validated['effective_from']))->modify('-1 day')->format('Y-m-d');

                    abort_if($closeAt < $current->effective_from, 422, 'Tanggal berlaku baru harus setelah tanggal mulai nilai yang sedang aktif.');

                    DB::table('pay_salary_scale')->where('id', $current->id)->update([
                        'effective_to' => $closeAt,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('pay_salary_scale')->insert([
                    'id' => $newRowId,
                    'person_grade' => $validated['person_grade'],
                    'step' => $validated['step'],
                    'amount_cents' => $amountCents,
                    'effective_from' => $validated['effective_from'],
                    'effective_to' => null,
                    'source_document' => $validated['source_document'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);
            });
        } catch (QueryException $e) {
            return redirect()->route('sysadmin.salary-scale.index')
                ->with('gagal', 'Rentang tanggal tumpang tindih dengan nilai lain untuk Person Grade/baris ini.');
        }

        $this->auditRepository->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'pay_salary_scale',
            auditableId: $newRowId,
            action: AuditAction::ParameterValueAdded,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.salary-scale.index')
            ->with('sukses', "Skala Imbalan Kerja PG{$validated['person_grade']} baris {$validated['step']} tersimpan.");
    }
}
