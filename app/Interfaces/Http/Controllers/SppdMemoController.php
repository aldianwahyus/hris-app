<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Money;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\ResolveEmployeeForHrAction;
use App\Modules\Sppd\Application\SubmitSppdMemoGroup;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\JabatanTierNotMapped;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdBudgetCalculator;
use App\Modules\Sppd\Domain\SppdTariffNotFound;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Temporal\Domain\AsOfDate;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * SPPD Massal — input SATU memo divisi berisi banyak pegawai sekaligus
 * (Admin HC bank-wide / Admin Cabang kantornya sendiri), auto-approve
 * (lihat SubmitSppdMemoGroup), + cetak Surat Jalan (1 per grup) dan
 * Rincian Lumpsum (1 per pegawai). Pola bank-wide/office-scope MENIRU
 * DecisionLetterController (satu controller bercabang di dalam, BUKAN
 * pola indexForHc/indexForBranch Lembur) — ini aksi input satu arah,
 * bukan antrean dua sisi.
 *
 * SENGAJA TIDAK menyentuh SppdRequestController/Api\V1\SppdApiController/
 * SppdApprovalController/SppdDisbursementController/DisburseSppdRequest —
 * jalur SPPD mandiri lama tetap berjalan persis seperti sebelumnya.
 */
final class SppdMemoController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ResolveEmployeeForHrAction $resolveEmployee,
        private readonly SubmitSppdMemoGroup $submitMemoGroup,
        private readonly SppdBudgetCalculator $calculator,
    ) {}

    public function index(): View
    {
        $bankWide = $this->isBankWide();

        $query = DB::table('spd_memo_groups as g')->select('g.*');

        if ($bankWide) {
            $query->where('g.payer_scope', 'hc');
        } else {
            $officeId = $this->actor->officeId();
            abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');
            $query->where('g.payer_scope', 'branch')->where('g.office_id', $officeId);
        }

        $groups = $query->orderByDesc('g.memo_date')->orderByDesc('g.created_at')->get();

        return view('admin.sppd-memo-index', compact('groups', 'bankWide'));
    }

    /**
     * Pratinjau lumpsum (jika pegawai+kategori+tanggal sudah dipilih)
     * dihitung ulang di sini SEMATA untuk ditampilkan — bukan dipercaya
     * saat pengajuan disimpan. store()/SubmitSppdMemoGroup menghitung
     * ulang dari nol secara independen (pola sama SppdRequestController).
     */
    public function create(Request $request): View
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

        // Daftar penandatangan SENGAJA bank-wide (bukan ikut lingkup picker
        // pegawai yang berangkat) — pejabat berwenang/penandatangan Rincian
        // Lumpsum lazimnya BUKAN salah satu pegawai yang berangkat, dan
        // bisa dari kantor/divisi mana pun.
        $signatoryEmployees = DB::table('emp_employees')->orderBy('full_name')->get(['id', 'full_name', 'nrp']);

        // Divisi asal — HANYA divisi yang benar-benar ada di Kantor Pusat
        // (memo lintas divisi selalu dari sana, lihat konteks bisnis di
        // docblock kelas ini), bukan teks bebas yang rawan salah ketik.
        $divisions = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('o.office_type', 'head_office')
            ->whereNotNull('e.division')
            ->distinct()
            ->orderBy('e.division')
            ->pluck('e.division');

        $tripCategories = TripCategory::cases();
        $radiusBands = RadiusBand::cases();
        $preview = null;
        $previewError = null;

        /** @var array<int, string> $selectedEmployeeIds */
        $selectedEmployeeIds = (array) $request->query('employee_ids', []);

        // Kehadiran trip_category di query menandakan form SUDAH pernah
        // disubmit (lewat "Hitung Perkiraan") — baru di titik itu pilihan
        // checkbox pengguna (termasuk yang sengaja dikosongkan semua)
        // dihormati; sebelum itu (muat halaman pertama kali) semua
        // komponen tampil tercentang secara default.
        $includedComponents = $request->filled('trip_category')
            ? array_values(array_intersect((array) $request->query('included_components', []), SubmitSppdMemoGroup::COMPONENT_KEYS))
            : SubmitSppdMemoGroup::COMPONENT_KEYS;

        $employeeComponentOptions = $this->parseEmployeeComponentOptions((array) $request->query('employee_options', []));

        if ($selectedEmployeeIds !== [] && $request->filled('trip_category') && $request->filled('start_date') && $request->filled('end_date')) {
            try {
                $preview = $this->computePreview($request, $selectedEmployeeIds, $includedComponents, $employeeComponentOptions);
            } catch (JabatanTierNotMapped|SppdTariffNotFound|InvalidArgumentException $e) {
                $previewError = $e->getMessage();
            }
        }

        return view('admin.sppd-memo-create', compact(
            'employees', 'bankWide', 'tripCategories', 'radiusBands', 'signatoryEmployees', 'divisions',
            'includedComponents', 'preview', 'previewError',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['uuid', 'exists:emp_employees,id'],
            'memo_number' => ['required', 'string', 'max:100'],
            'memo_date' => ['required', 'date'],
            'source_division' => ['nullable', 'string', 'max:150'],
            'trip_category' => ['required', 'string', 'in:'.implode(',', array_column(TripCategory::cases(), 'value'))],
            'radius_band' => ['nullable', 'string', 'in:'.implode(',', array_column(RadiusBand::cases(), 'value'))],
            'destination' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'included_components' => ['required', 'array', 'min:1'],
            'included_components.*' => ['string', 'in:'.implode(',', SubmitSppdMemoGroup::COMPONENT_KEYS)],
            'employee_options' => ['nullable', 'array'],
            'employee_options.*' => ['array'],
            'employee_options.*.*.percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'employee_options.*.*.days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'authorizing_official_title' => ['nullable', 'string', 'max:150'],
            'authorizing_official_name' => ['nullable', 'string', 'max:150'],
            'lumpsum_signatory_1_title' => ['nullable', 'string', 'max:150'],
            'lumpsum_signatory_1_name' => ['nullable', 'string', 'max:150'],
            'lumpsum_signatory_2_title' => ['nullable', 'string', 'max:150'],
            'lumpsum_signatory_2_name' => ['nullable', 'string', 'max:150'],
        ], [
            'included_components.required' => 'Pilih minimal satu komponen lumpsum yang akan diberikan.',
            'included_components.min' => 'Pilih minimal satu komponen lumpsum yang akan diberikan.',
        ]);

        // Lingkup DIVALIDASI DULU untuk SELURUH daftar (pola sama
        // DecisionLetterController::store()) — resolveEmployee->handle()
        // memakai abort_unless() (403 langsung), jadi diperiksa sebelum
        // transaksi penyimpanan dimulai, bukan di tengah jalan.
        foreach ($validated['employee_ids'] as $employeeId) {
            $this->resolveEmployee->handle($employeeId, $this->actor->roles(), $this->actor->officeId());
        }

        $bankWide = $this->isBankWide();
        $payerScope = $bankWide ? 'hc' : 'branch';
        $officeId = $bankWide ? null : $this->actor->officeId();

        try {
            $groupId = $this->submitMemoGroup->handle(
                employeeIds: $validated['employee_ids'],
                memoNumber: $validated['memo_number'],
                memoDate: new DateTimeImmutable($validated['memo_date']),
                sourceDivision: $validated['source_division'] ?? null,
                tripCategory: TripCategory::from($validated['trip_category']),
                destination: $validated['destination'],
                purpose: $validated['purpose'],
                startDate: new DateTimeImmutable($validated['start_date']),
                endDate: new DateTimeImmutable($validated['end_date']),
                radiusBand: isset($validated['radius_band']) ? RadiusBand::from($validated['radius_band']) : null,
                includedComponents: $validated['included_components'],
                employeeComponentOptions: $this->parseEmployeeComponentOptions($validated['employee_options'] ?? []),
                authorizingOfficialTitle: $validated['authorizing_official_title'] ?? null,
                authorizingOfficialName: $validated['authorizing_official_name'] ?? null,
                lumpsumSignatory1Title: $validated['lumpsum_signatory_1_title'] ?? null,
                lumpsumSignatory1Name: $validated['lumpsum_signatory_1_name'] ?? null,
                lumpsumSignatory2Title: $validated['lumpsum_signatory_2_title'] ?? null,
                lumpsumSignatory2Name: $validated['lumpsum_signatory_2_name'] ?? null,
                payerScope: $payerScope,
                officeId: $officeId,
                actor: $this->currentAuditActor($request),
            );
        } catch (DomainException|JabatanTierNotMapped|SppdTariffNotFound|InvalidArgumentException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('sppd-memo.show', $groupId)->with('sukses', 'SPPD massal tersimpan dan langsung disetujui.');
    }

    public function show(string $id): View
    {
        /** @var object{payer_scope: string, office_id: ?string, group_number: string}|null $group */
        $group = DB::table('spd_memo_groups')->where('id', $id)->first();
        abort_if($group === null, 404);
        $this->guardMemoAccess($group);

        $travelers = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->where('r.memo_group_id', $id)
            ->select(
                'r.id', 'r.request_number', 'r.status', 'r.uang_makan_cents', 'r.uang_saku_cents',
                'r.estimasi_hotel_cents', 'r.hotel_kompensasi_cents', 'r.estimasi_angkutan_setempat_cents', 'r.estimasi_transportasi_tujuan_cents',
                'r.uang_makan_h1_cents', 'r.uang_saku_h1_cents', 'r.uang_makan_konsumsi_cents',
                'e.full_name', 'e.nrp',
            )
            ->orderBy('e.full_name')
            ->get();

        return view('admin.sppd-memo-show', compact('group', 'travelers'));
    }

    public function printSuratJalan(string $id): Response
    {
        /** @var object{payer_scope: string, office_id: ?string, group_number: string}|null $group */
        $group = DB::table('spd_memo_groups')->where('id', $id)->first();
        abort_if($group === null, 404);
        $this->guardMemoAccess($group);

        $travelers = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('r.memo_group_id', $id)
            ->select('e.full_name', 'e.nrp', 'e.person_grade', 'p.name as position_name', 'o.name as office_name')
            ->orderBy('e.full_name')
            ->get();

        $headOfficeAddress = DB::table('md_offices')->where('office_type', 'head_office')->value('address');
        $slug = str_replace('/', '-', $group->group_number);

        return Pdf::loadView('admin.sppd-memo-surat-jalan', compact('group', 'travelers', 'headOfficeAddress'))
            ->stream("Surat-Jalan-{$slug}.pdf");
    }

    public function printRincianLumpsum(string $id, string $requestId): Response
    {
        /** @var object{payer_scope: string, office_id: ?string, group_number: string}|null $group */
        $group = DB::table('spd_memo_groups')->where('id', $id)->first();
        abort_if($group === null, 404);
        $this->guardMemoAccess($group);

        /**
         * @var object{memo_group_id: ?string, nrp: string, trip_category: string, jabatan_tier: ?string,
         *     radius_band: ?string, start_date: string, total_days: int, component_options_snapshot: ?string,
         *     hotel_kompensasi_cents: ?int, estimasi_transportasi_tujuan_cents: ?int,
         *     estimasi_angkutan_setempat_cents: ?int, uang_makan_cents: int}|null $traveler
         */
        $traveler = DB::table('spd_requests as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->where('r.id', $requestId)
            ->select('r.*', 'e.full_name', 'e.nrp', 'e.person_grade', 'p.name as position_name')
            ->first();

        abort_if($traveler === null || $traveler->memo_group_id !== $id, 404);

        $slug = str_replace('/', '-', $group->group_number).'-'.$traveler->nrp;
        $baris = $this->rincianLumpsumBaris($traveler);

        return Pdf::loadView('admin.sppd-memo-rincian-lumpsum', compact('group', 'traveler', 'baris'))
            ->stream("Rincian-Lumpsum-{$slug}.pdf");
    }

    /**
     * Merekonstruksi baris persentase formulir resmi ("100% Dari Tarif",
     * "75%", dst.) — tarif DASAR per-hari/unit dihitung ULANG lewat
     * SppdBudgetCalculator::compute() (read-only, sama seperti
     * pratinjau/submit, TIDAK menduplikasi sumber tarif), lalu
     * dikombinasikan dengan component_options_snapshot (persen+hari yang
     * SUNGGUHAN dipakai baris ini) untuk menentukan hari mana yang
     * benar-benar terisi vs. 0 (baris bawaan formulir yang tidak
     * berlaku di sistem kita — mis. 75%/50%/Ditanggung Penyelenggara,
     * dikonfirmasi user tidak ada tarif BPP untuk itu).
     *
     * Rate DITAMPILKAN untuk SEMUA baris (arithmetic murni dari tarif
     * dasar × persentase) — PERSIS pola formulir resmi (baris tak
     * terpakai tetap menunjukkan tarifnya, hanya hari & totalnya 0).
     *
     * @param  object{trip_category: string, jabatan_tier: ?string, radius_band: ?string, start_date: string, total_days: int, component_options_snapshot: ?string, hotel_kompensasi_cents: ?int, estimasi_transportasi_tujuan_cents: ?int, estimasi_angkutan_setempat_cents: ?int, uang_makan_cents: int}  $traveler
     * @return array<string, mixed>
     */
    private function rincianLumpsumBaris(object $traveler): array
    {
        $category = TripCategory::from($traveler->trip_category);
        $tier = $traveler->jabatan_tier !== null ? JabatanTier::from($traveler->jabatan_tier) : null;
        $radiusBand = $traveler->radius_band !== null ? RadiusBand::from($traveler->radius_band) : null;
        $asOf = AsOfDate::on(new DateTimeImmutable($traveler->start_date));

        $budget = $this->calculator->compute($category, $tier, $radiusBand, (int) $traveler->total_days, $asOf);

        $snapshot = $traveler->component_options_snapshot !== null
            ? json_decode((string) $traveler->component_options_snapshot, true)
            : [];

        $perDay = fn (Money $base, int $units): int => intdiv($base->cents, max($units, 1));

        $uangMakanPerHari = $perDay($budget->uangMakan, (int) $traveler->total_days);
        $uangSakuPerHari = $perDay($budget->uangSaku, (int) $traveler->total_days);
        $angkutanPerHari = $budget->angkutanSetempat !== null ? $perDay($budget->angkutanSetempat, (int) $traveler->total_days) : 0;
        $hotelKompensasiPerHari = $budget->hotelKompensasi !== null ? $perDay($budget->hotelKompensasi, (int) $traveler->total_days) : 0;

        // Nama kolom cents TIDAK SELALU "{componentKey}_cents" — 3 dari 9
        // kolom budget punya prefiks "estimasi_" (estimasi_hotel_cents,
        // estimasi_angkutan_setempat_cents, estimasi_transportasi_tujuan_cents).
        // Bug ditemukan lewat verifikasi visual PDF: baris "Uang Angkutan
        // setempat" tadinya selalu 0 Hari walau komponennya tercentang,
        // karena kode SEBELUMNYA menebak nama kolom "angkutan_setempat_cents"
        // (tidak ada) alih-alih kolom sungguhan di bawah ini.
        $centsColumn = fn (string $componentKey): string => match ($componentKey) {
            'hotel' => 'estimasi_hotel_cents',
            'angkutan_setempat' => 'estimasi_angkutan_setempat_cents',
            'transportasi_tujuan' => 'estimasi_transportasi_tujuan_cents',
            default => $componentKey.'_cents',
        };

        // Baris = [persen => componentKey] — komponen mana (kalau ada) yang
        // mengisi baris persentase itu di formulir resmi. Baris tanpa
        // komponen (75/50/Ditanggung Penyelenggara/25% Angkutan) TIDAK
        // PERNAH punya hari (dikonfirmasi user).
        $isiBaris = function (array $peta, int $perHariRate) use ($snapshot, $traveler, $centsColumn): array {
            $hasil = [];

            foreach ($peta as $persen => $componentKey) {
                $days = 0;

                if ($componentKey !== null
                    && ($traveler->{$centsColumn($componentKey)} ?? null) !== null
                    && (int) ($snapshot[$componentKey]['percent'] ?? -1) === $persen
                ) {
                    $days = (int) ($snapshot[$componentKey]['days'] ?? 0);
                }

                $rate = (int) round($perHariRate * $persen / 100);
                $hasil[$persen] = ['rate' => $rate, 'days' => $days, 'total' => $rate * $days];
            }

            return $hasil;
        };

        // Jarak Pendek (berbasis radius) memakai baris RADIUS untuk Uang
        // Makan, BUKAN baris persentase — dua keluarga baris ini SALING
        // MENIADAKAN tergantung kategori, persis pemisahan cabang di
        // SppdBudgetCalculator::compute() (radius vs. jenjang jabatan).
        // Tanpa gerbang ini, uang_makan_cents/uang_saku_cents (kolom NOT
        // NULL, selalu 0 untuk Jarak Pendek) bisa salah tampil dobel di
        // baris 100% SEKALIGUS baris radius.
        $berbasisRadius = $category->berbasisRadius();

        return [
            'uang_makan' => $berbasisRadius ? $isiBaris([100 => null, 75 => null, 70 => null, 50 => null, 30 => null, 25 => null], 0) : $isiBaris([
                100 => 'uang_makan', 75 => null, 70 => 'uang_makan_konsumsi',
                50 => null, 30 => 'uang_makan_konsumsi', 25 => 'uang_makan_h1',
            ], $uangMakanPerHari),
            'uang_saku' => $berbasisRadius ? $isiBaris([100 => null, 50 => null, 25 => null], 0) : $isiBaris([100 => 'uang_saku', 50 => null, 25 => 'uang_saku_h1'], $uangSakuPerHari),
            'angkutan_setempat' => $isiBaris([100 => 'angkutan_setempat', 25 => null], $angkutanPerHari),
            'hotel_kompensasi' => ['rate' => $hotelKompensasiPerHari, 'days' => $traveler->hotel_kompensasi_cents !== null ? (int) ($snapshot['hotel_kompensasi']['days'] ?? 0) : 0, 'total' => $traveler->hotel_kompensasi_cents ?? 0],
            'transportasi_tujuan' => ['rate' => $budget->transportasiTujuan === null ? 0 : $budget->transportasiTujuan->cents, 'days' => $traveler->estimasi_transportasi_tujuan_cents !== null ? 1 : 0, 'total' => $traveler->estimasi_transportasi_tujuan_cents ?? 0],
            'radius' => [
                '30_100' => $radiusBand === RadiusBand::Km30To100 ? (int) $traveler->uang_makan_cents : 0,
                '100_150' => $radiusBand === RadiusBand::Km100To150 ? (int) $traveler->uang_makan_cents : 0,
                '150_plus' => $radiusBand === RadiusBand::KmAbove150 ? (int) $traveler->uang_makan_cents : 0,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $employeeIds
     * @param  array<int, string>  $includedComponents
     * @param  array<string, array<string, array{percent: int, days: int}>>  $employeeComponentOptions
     * @return array<int, array{employee_id: string, full_name: string, nrp: string, currency: string, cents: array{uang_makan_cents: int, uang_saku_cents: int, estimasi_hotel_cents: ?int, hotel_kompensasi_cents: ?int, estimasi_angkutan_setempat_cents: ?int, estimasi_transportasi_tujuan_cents: ?int, uang_makan_h1_cents: ?int, uang_saku_h1_cents: ?int, uang_makan_konsumsi_cents: ?int}, options: array<string, array{percent: int, days: int}>}>
     */
    private function computePreview(Request $request, array $employeeIds, array $includedComponents, array $employeeComponentOptions): array
    {
        $category = TripCategory::from($request->string('trip_category')->toString());
        $startDate = new DateTimeImmutable((string) $request->string('start_date'));
        $endDate = new DateTimeImmutable((string) $request->string('end_date'));

        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $totalDays = $startDate->diff($endDate)->days + 1;
        $radiusBand = $request->filled('radius_band') ? RadiusBand::from($request->string('radius_band')->toString()) : null;
        $asOf = AsOfDate::on($startDate);

        $rows = [];

        foreach ($employeeIds as $employeeId) {
            $employee = DB::table('emp_employees as e')
                ->join('md_positions as p', 'p.id', '=', 'e.position_id')
                ->where('e.id', $employeeId)
                ->select('e.full_name', 'e.nrp', 'p.sppd_jabatan_tier')
                ->first();

            if ($employee === null) {
                continue;
            }

            $tier = null;

            if (! $category->berbasisRadius()) {
                if ($employee->sppd_jabatan_tier === null) {
                    throw JabatanTierNotMapped::forEmployee($employeeId);
                }

                $tier = JabatanTier::from($employee->sppd_jabatan_tier);
            }

            $budget = $this->calculator->compute($category, $tier, $radiusBand, $totalDays, $asOf);
            $options = $employeeComponentOptions[$employeeId] ?? [];

            $resolvedOptions = [];
            foreach (SubmitSppdMemoGroup::COMPONENT_KEYS as $key) {
                $resolvedOptions[$key] = $options[$key] ?? SubmitSppdMemoGroup::defaultOption($key, $totalDays);
            }

            $rows[] = [
                'employee_id' => $employeeId,
                'full_name' => $employee->full_name,
                'nrp' => $employee->nrp,
                'currency' => $budget->mataUang,
                'cents' => SubmitSppdMemoGroup::computeCents($budget, $totalDays, $includedComponents, $options, $category),
                'options' => $resolvedOptions,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<mixed, mixed>  $raw  request-shaped employee_options[employeeId][component][percent|days]
     * @return array<string, array<string, array{percent: int, days: int}>>
     */
    private function parseEmployeeComponentOptions(array $raw): array
    {
        $parsed = [];

        foreach ($raw as $employeeId => $components) {
            if (! is_string($employeeId) || ! is_array($components)) {
                continue;
            }

            foreach ($components as $componentKey => $option) {
                if (! is_string($componentKey) || ! in_array($componentKey, SubmitSppdMemoGroup::COMPONENT_KEYS, true) || ! is_array($option)) {
                    continue;
                }

                $percent = isset($option['percent']) && is_numeric($option['percent']) ? (int) $option['percent'] : null;
                $days = isset($option['days']) && is_numeric($option['days']) ? (int) $option['days'] : null;

                if ($percent === null || $days === null) {
                    continue;
                }

                $parsed[$employeeId][$componentKey] = ['percent' => $percent, 'days' => $days];
            }
        }

        return $parsed;
    }

    /** @param object{payer_scope: string, office_id: ?string, group_number: string} $group */
    private function guardMemoAccess(object $group): void
    {
        if ($group->payer_scope === 'hc') {
            abort_unless($this->actor->hasRole('hr_approver'), 404);

            return;
        }

        abort_unless($group->office_id === $this->actor->officeId() || $this->actor->hasRole('hr_approver'), 404);
    }

    private function isBankWide(): bool
    {
        return $this->actor->hasRole('hr_approver');
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
