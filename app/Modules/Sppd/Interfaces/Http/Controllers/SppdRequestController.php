<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Interfaces\Http\Controllers;

use App\Modules\Sppd\Application\CancelSppdRequest;
use App\Modules\Sppd\Application\SubmitSppdRequest;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\JabatanTierNotMapped;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdBudgetCalculator;
use App\Modules\Sppd\Domain\SppdBudgetResult;
use App\Modules\Sppd\Domain\SppdTariffNotFound;
use App\Modules\Sppd\Domain\TripCategory;
use App\Modules\Sppd\Interfaces\Http\Requests\SubmitSppdRequestForm;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

final class SppdRequestController
{
    public function __construct(
        private readonly SubmitSppdRequest $submit,
        private readonly SppdBudgetCalculator $calculator,
        private readonly CancelSppdRequest $cancel,
    ) {}

    /** Riwayat SPPD Saya — SEMUA pengajuan milik pegawai yang login. Belum pernah ada halaman riwayat sama sekali sebelum ini. */
    public function history(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('spd_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('sppd.history', compact('requests'));
    }

    public function cancelRequest(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $this->cancel->handle(
                sppdRequestId: $id,
                employeeId: $user->employee_id,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'Pengajuan SPPD berhasil dibatalkan.');
    }

    /**
     * Pratinjau anggaran (jika kategori+tanggal sudah dipilih) dihitung
     * ulang di sini SEMATA untuk ditampilkan — bukan dipercaya saat
     * pengajuan disimpan. store() menghitung ulang dari nol secara
     * independen, sehingga pratinjau ini tidak bisa dipalsukan lewat
     * devtools untuk mengubah nilai yang benar-benar tersimpan.
     */
    public function create(Request $request): View
    {
        $tripCategories = TripCategory::cases();
        $radiusBands = RadiusBand::cases();
        $preview = null;
        $previewError = null;

        if ($request->filled('trip_category') && $request->filled('start_date') && $request->filled('end_date')) {
            try {
                $preview = $this->computePreview($request);
            } catch (JabatanTierNotMapped|SppdTariffNotFound|InvalidArgumentException $e) {
                $previewError = $e->getMessage();
            }
        }

        return view('sppd.create', compact('tripCategories', 'radiusBands', 'preview', 'previewError'));
    }

    public function store(SubmitSppdRequestForm $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        try {
            $requestNumber = $this->submit->handle(
                employeeId: $user->employee_id,
                tripCategory: TripCategory::from($request->string('trip_category')->toString()),
                destination: (string) $request->string('destination'),
                purpose: (string) $request->string('purpose'),
                startDate: new DateTimeImmutable((string) $request->string('start_date')),
                endDate: new DateTimeImmutable((string) $request->string('end_date')),
                radiusBand: $request->filled('radius_band') ? RadiusBand::from($request->string('radius_band')->toString()) : null,
                actor: new AuditActor(
                    actorId: $user->employee_id,
                    actorRole: implode(',', $user->getRoleNames()->all()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (JabatanTierNotMapped|SppdTariffNotFound|InvalidArgumentException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('ess.dashboard')
            ->with('sukses', "Pengajuan SPPD {$requestNumber} berhasil dikirim dan menunggu persetujuan.");
    }

    private function computePreview(Request $request): SppdBudgetResult
    {
        $user = $request->user();

        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $category = TripCategory::from($request->string('trip_category')->toString());
        $startDate = new DateTimeImmutable((string) $request->string('start_date'));
        $endDate = new DateTimeImmutable((string) $request->string('end_date'));

        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $totalDays = $startDate->diff($endDate)->days + 1;
        $radiusBand = $request->filled('radius_band') ? RadiusBand::from($request->string('radius_band')->toString()) : null;

        $tier = null;

        if (! $category->berbasisRadius()) {
            $tierValue = DB::table('emp_employees as e')
                ->join('md_positions as p', 'p.id', '=', 'e.position_id')
                ->where('e.id', $user->employee_id)
                ->value('p.sppd_jabatan_tier');

            if ($tierValue === null) {
                throw JabatanTierNotMapped::forEmployee($user->employee_id);
            }

            $tier = JabatanTier::from($tierValue);
        }

        return $this->calculator->compute($category, $tier, $radiusBand, $totalDays, AsOfDate::on($startDate));
    }
}
