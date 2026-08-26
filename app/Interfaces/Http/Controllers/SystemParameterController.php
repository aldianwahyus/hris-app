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
 * Konfigurasi parameter kebijakan (cfg_parameters/cfg_parameter_values)
 * — lingkup TEKNIS Admin Sistem (Role::SystemAdmin), BUKAN data
 * transaksi pegawai perorangan (gaji/cuti/lembur individu tidak pernah
 * disentuh di sini).
 *
 * CATATAN JUJUR: angka-angka ini (tarif lembur, minimal block leave,
 * persentase iuran) SESUNGGUHNYA kebijakan SDM/Keuangan, bukan murni
 * teknis — layar ini sengaja diberikan ke Admin Sistem sesuai
 * kesepakatan eksplisit, dengan pagar: append-only (riwayat tidak
 * pernah ditimpa/dihapus, sejalan ParameterResolver §5.1) dan audit
 * trail penuh yang dapat ditinjau independen oleh Auditor. Pertimbangkan
 * maker-checker terpisah (mis. paraf HrApprover) bila kelak dipakai
 * sungguhan — belum dibangun di sini karena di luar kesepakatan saat ini.
 */
final class SystemParameterController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $auditRepository,
    ) {}

    public function index(): View
    {
        $today = now()->toDateString();

        $parameters = DB::table('cfg_parameters as p')
            ->leftJoin('cfg_parameter_values as v', function ($join) use ($today) {
                $join->on('v.parameter_id', '=', 'p.id')
                    ->whereNull('v.deleted_at')
                    ->whereDate('v.effective_from', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('v.effective_to')->orWhereDate('v.effective_to', '>=', $today);
                    });
            })
            ->whereNull('p.deleted_at')
            ->select(
                'p.id', 'p.code', 'p.name', 'p.value_type', 'p.unit', 'p.owner_module',
                'v.value as nilai_saat_ini', 'v.effective_from', 'v.source_document'
            )
            ->orderBy('p.owner_module')
            ->orderBy('p.code')
            ->get();

        return view('admin.system-parameters', compact('parameters'));
    }

    public function history(string $id): View
    {
        $parameter = DB::table('cfg_parameters')->where('id', $id)->whereNull('deleted_at')->first();
        abort_if($parameter === null, 404);

        $values = DB::table('cfg_parameter_values')
            ->where('parameter_id', $id)
            ->whereNull('deleted_at')
            ->orderByDesc('effective_from')
            ->get();

        return view('admin.system-parameter-history', compact('parameter', 'values'));
    }

    public function addValue(Request $request, string $id): RedirectResponse
    {
        $parameter = DB::table('cfg_parameters')->where('id', $id)->whereNull('deleted_at')->first();
        abort_if($parameter === null, 404);

        $validated = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'source_document' => ['nullable', 'string', 'max:150'],
        ]);

        $this->guardValueMatchesType($parameter->value_type, $validated['value']);

        try {
            DB::transaction(function () use ($id, $validated) {
                $now = new DateTimeImmutable;

                // Nilai yang sedang aktif (belum ada batas akhir) DITUTUP,
                // BUKAN dihapus/ditimpa — riwayatnya tetap utuh untuk
                // penghitungan ulang historis (ParameterResolver §5.1).
                $current = DB::table('cfg_parameter_values')
                    ->where('parameter_id', $id)
                    ->whereNull('effective_to')
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($current !== null) {
                    $closeAt = (new DateTimeImmutable($validated['effective_from']))->modify('-1 day')->format('Y-m-d');

                    abort_if(
                        $closeAt < $current->effective_from,
                        422,
                        'Tanggal berlaku baru harus setelah tanggal mulai nilai yang sedang aktif.'
                    );

                    DB::table('cfg_parameter_values')->where('id', $current->id)->update([
                        'effective_to' => $closeAt,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('cfg_parameter_values')->insert([
                    'id' => (string) Uuid7::generate(),
                    'parameter_id' => $id,
                    'value' => $validated['value'],
                    'effective_from' => $validated['effective_from'],
                    'effective_to' => null,
                    'source_document' => $validated['source_document'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);
            });
        } catch (QueryException $e) {
            return redirect()->route('sysadmin.parameters.index')
                ->with('gagal', 'Rentang tanggal tumpang tindih dengan nilai lain untuk parameter ini — periksa riwayat.');
        }

        $this->auditRepository->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'cfg_parameter',
            auditableId: $id,
            action: AuditAction::ParameterValueAdded,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.parameters.index')
            ->with('sukses', "Nilai baru untuk {$parameter->code} tersimpan, berlaku sejak {$validated['effective_from']}.");
    }

    /** Mencegah nilai yang tidak sesuai tipe merusak ParameterResolver di sisi bisnis (mis. teks masuk ke parameter integer). */
    private function guardValueMatchesType(string $valueType, string $value): void
    {
        $valid = match ($valueType) {
            'integer' => (bool) preg_match('/^-?\d+$/', $value),
            'decimal', 'money' => is_numeric($value),
            'boolean' => in_array(strtolower($value), ['true', 'false', '1', '0'], true),
            default => true, // string
        };

        abort_unless($valid, 422, "Nilai \"{$value}\" tidak sesuai tipe parameter ({$valueType}).");
    }
}
