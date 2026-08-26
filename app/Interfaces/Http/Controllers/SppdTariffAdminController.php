<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdTariffComponent;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Tarif SPPD (BPP/442/03/64/2026) — lingkup TEKNIS Admin Sistem, pola
 * sama seperti Skala Imbalan Kerja: matriks (komponen × kategori ×
 * jenjang/pita radius), nilai lama ditutup bukan ditimpa.
 */
final class SppdTariffAdminController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $auditRepository,
    ) {}

    public function index(): View
    {
        $today = now()->toDateString();

        $rows = DB::table('pay_sppd_tariffs')
            ->whereNull('deleted_at')
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->orderBy('component')
            ->orderBy('trip_category')
            ->orderBy('jabatan_tier')
            ->get();

        $components = SppdTariffComponent::cases();
        $categories = TripCategory::cases();
        $tiers = JabatanTier::cases();
        $radiusBands = RadiusBand::cases();

        return view('admin.sppd-tariffs', compact('rows', 'components', 'categories', 'tiers', 'radiusBands'));
    }

    public function addValue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'component' => ['required', new Enum(SppdTariffComponent::class)],
            'trip_category' => ['required', new Enum(TripCategory::class)],
            'jabatan_tier' => ['nullable', new Enum(JabatanTier::class)],
            'radius_band' => ['nullable', new Enum(RadiusBand::class)],
            'currency' => ['required', 'in:IDR,USD'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'source_document' => ['nullable', 'string', 'max:150'],
        ]);

        $tier = $validated['jabatan_tier'] ?? null;
        $radiusBand = $validated['radius_band'] ?? null;
        $amountCents = (int) round(((float) $validated['amount']) * 100);
        $newRowId = (string) Uuid7::generate();

        try {
            DB::transaction(function () use ($validated, $tier, $radiusBand, $amountCents, $newRowId) {
                $now = new DateTimeImmutable;

                $query = DB::table('pay_sppd_tariffs')
                    ->where('component', $validated['component'])
                    ->where('trip_category', $validated['trip_category'])
                    ->whereNull('effective_to')
                    ->whereNull('deleted_at');

                $tier === null ? $query->whereNull('jabatan_tier') : $query->where('jabatan_tier', $tier);
                $radiusBand === null ? $query->whereNull('radius_band') : $query->where('radius_band', $radiusBand);

                $current = $query->lockForUpdate()->first();

                if ($current !== null) {
                    $closeAt = (new DateTimeImmutable($validated['effective_from']))->modify('-1 day')->format('Y-m-d');

                    abort_if($closeAt < $current->effective_from, 422, 'Tanggal berlaku baru harus setelah tanggal mulai nilai yang sedang aktif.');

                    DB::table('pay_sppd_tariffs')->where('id', $current->id)->update([
                        'effective_to' => $closeAt,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('pay_sppd_tariffs')->insert([
                    'id' => $newRowId,
                    'component' => $validated['component'],
                    'trip_category' => $validated['trip_category'],
                    'jabatan_tier' => $tier,
                    'radius_band' => $radiusBand,
                    'currency' => $validated['currency'],
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
            return redirect()->route('sysadmin.sppd-tariffs.index')
                ->with('gagal', 'Rentang tanggal tumpang tindih dengan nilai lain untuk kombinasi ini.');
        }

        $this->auditRepository->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'pay_sppd_tariff',
            auditableId: $newRowId,
            action: AuditAction::ParameterValueAdded,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.sppd-tariffs.index')->with('sukses', 'Tarif SPPD baru tersimpan.');
    }
}
