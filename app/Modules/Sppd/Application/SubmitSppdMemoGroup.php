<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Application;

use App\Core\Domain\Money;
use App\Core\Domain\Uuid7;
use App\Modules\Sppd\Domain\JabatanTier;
use App\Modules\Sppd\Domain\JabatanTierNotMapped;
use App\Modules\Sppd\Domain\RadiusBand;
use App\Modules\Sppd\Domain\SppdBudgetCalculator;
use App\Modules\Sppd\Domain\SppdBudgetResult;
use App\Modules\Sppd\Domain\TripCategory;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use App\Shared\Temporal\Domain\AsOfDate;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * SPPD Massal — input SATU memo divisi yang mencakup BANYAK pegawai
 * sekaligus (Admin HC/Admin Cabang menerima memo fisik, bukan
 * masing-masing pegawai mengajukan sendiri). BERBEDA dari
 * SubmitSppdRequest (ESS, selalu 'pending', selalu satu pegawai atas
 * nama dirinya sendiri) — di sini SETIAP baris LANGSUNG 'approved'
 * dengan approver_id=NULL, karena memo itu sendiri ADALAH jejak
 * persetujuan (bypass 2 tahap Atasan Langsung/Pimpinan Kantor yang
 * berlaku untuk pengajuan mandiri). approver_id=NULL membuat baris ini
 * otomatis TIDAK PERNAH muncul di SppdDisbursementController::baseQuery()
 * (INNER JOIN ke approver_id) maupun di antrean SppdApprovalController
 * (hanya memfilter status pending/pending_pimpinan) — TANPA mengubah
 * kode kedua controller itu sama sekali.
 *
 * Logika tarif memakai ULANG SppdBudgetCalculator (SAMA seperti
 * SubmitSppdRequest) — tidak menduplikasi aturan tarif, hanya logika
 * penyimpanan yang beda (massal + auto-approve).
 *
 * SEMUA pegawai dalam satu memo disimpan dalam SATU DB::transaction —
 * all-or-nothing. Satu memo = satu jejak berkas (Surat Jalan mencakup
 * semua nama dalam grup), jadi kegagalan menghitung anggaran satu
 * pegawai (tier belum dipetakan/tarif tidak ditemukan) membatalkan
 * SELURUH grup, bukan menyisakan grup yang pincang.
 *
 * `$includedComponents` — Admin HC/Admin Cabang bisa memilih SEBAGIAN
 * komponen lumpsum saja untuk dilimpahkan pada memo ini (mis. hanya
 * Uang Saku, atau hanya Uang Makan) alih-alih selalu seluruh komponen
 * yang berlaku untuk kategori perjalanan tersebut — ini gerbang
 * SELURUH GRUP (sama untuk semua pegawai).
 *
 * `$employeeComponentOptions` — SETIAP pegawai bisa punya persentase dan
 * jumlah hari SENDIRI per komponen (mis. menerapkan aturan H-1/H+1 BPP
 * §III.B.3 — 25% pada hari transit — untuk satu pegawai yang pulang
 * lebih awal, tanpa mengubah pegawai lain di grup yang sama). Keyed
 * `[employeeId][componentKey] => ['percent' => int, 'days' => int]`;
 * pasangan yang tidak diisi memakai bawaan defaultOptionFor() (100%,
 * total hari perjalanan — KECUALI transportasi_tujuan yang baku 1 hari
 * karena BPP menyebutnya biaya sekali jalan, bukan per hari).
 *
 * SppdBudgetCalculator tetap dihitung PENUH dulu (tidak diubah/
 * diduplikasi) untuk mendapat tarif per-hari/per-unit — computeCents()
 * baru MENSKALA hasilnya (persen × hari) lalu menyaring komponen yang
 * tidak dicentang, jadi angka yang tersimpan untuk komponen yang tidak
 * dicentang selalu 0 (uang_makan/uang_saku, kolom NOT NULL) atau NULL
 * (hotel/angkutan/transportasi, kolom nullable — konsisten dengan makna
 * "tidak berlaku" yang sudah dipakai kategori seperti Jarak Pendek/
 * Pindah/Detasir/Luar Negeri).
 */
final class SubmitSppdMemoGroup
{
    /** @var array<int, string> */
    public const COMPONENT_KEYS = [
        'uang_makan', 'uang_saku', 'hotel', 'hotel_kompensasi', 'angkutan_setempat', 'transportasi_tujuan',
        'uang_makan_h1', 'uang_saku_h1', 'uang_makan_konsumsi',
    ];

    public function __construct(
        private readonly SppdBudgetCalculator $calculator,
        private readonly AuditRepository $audit,
    ) {}

    /**
     * Bawaan persen/hari saat admin tidak mengisi override untuk
     * pasangan pegawai+komponen tertentu.
     *
     * `uang_makan_h1`/`uang_saku_h1` (BPP §III.B.3, hari transit H-1/H+1)
     * baku 25%×1 hari — SENGAJA bukan $totalDays, karena secara BPP ini
     * cuma 1-2 hari transit dari perjalanan yang bisa berhari-hari, BUKAN
     * seluruh durasi trip (bug yang ditemukan dari percobaan "preset"
     * sebelumnya yang salah menerapkan 25% ke $totalDays penuh). Admin
     * WAJIB menambah baris ini sendiri kalau ada 2 hari transit (H-1 DAN
     * H+1) dengan menaikkan hari-nya jadi 2, dan MENGURANGI hari pada
     * uang_makan/uang_saku biasa sebesar itu supaya tidak dobel hitung.
     *
     * `uang_makan_konsumsi` (BPP §III.B.4, katering ditanggung panitia)
     * baku 70%×$totalDays (skenario paling umum — panitia menanggung 1x
     * makan) — admin ubah ke 30% bila panitia menanggung 2x makan (siang
     * DAN malam), atau kurangi hari-nya bila katering itu HANYA berlaku
     * sebagian hari trip. TIDAK ADA aturan setara untuk Uang Saku pada
     * BPP ini — SENGAJA tidak dibuatkan komponen "uang_saku_konsumsi".
     *
     * @return array{percent: int, days: int}
     */
    public static function defaultOption(string $componentKey, int $totalDays): array
    {
        return match ($componentKey) {
            'transportasi_tujuan' => ['percent' => 100, 'days' => 1],
            'uang_makan_h1', 'uang_saku_h1' => ['percent' => 25, 'days' => 1],
            'uang_makan_konsumsi' => ['percent' => 70, 'days' => $totalDays],
            default => ['percent' => 100, 'days' => $totalDays],
        };
    }

    /**
     * Menyaring SppdBudgetResult menjadi kolom-kolom cents SIAP SIMPAN,
     * menerapkan pilihan komponen ($includedComponents) DAN persen/hari
     * per komponen ($componentOptions, milik SATU pegawai) — dipakai
     * ULANG oleh SppdMemoController::computePreview() supaya pratinjau
     * di form PERSIS mencerminkan apa yang akan benar-benar tersimpan.
     *
     * $componentOptions keyed componentKey => ['percent' => int, 'days' => int].
     * Tarif dasar dari SppdBudgetCalculator (dihitung untuk $totalDays,
     * kecuali transportasiTujuan yang sudah flat/tidak dikali hari) DIBAGI
     * dulu untuk mendapat tarif per-hari/per-unit, baru dikalikan ULANG
     * dengan persen/hari pilihan admin — BUKAN mengganti logika tarif
     * BPP, hanya menyesuaikan berapa hari/persen dari tarif itu yang
     * benar-benar dibayarkan ke pegawai ini.
     *
     * @param  array<int, string>  $includedComponents
     * @param  array<string, array{percent: int, days: int}>  $componentOptions
     * @return array{uang_makan_cents: int, uang_saku_cents: int, estimasi_hotel_cents: ?int, hotel_kompensasi_cents: ?int, estimasi_angkutan_setempat_cents: ?int, estimasi_transportasi_tujuan_cents: ?int, uang_makan_h1_cents: ?int, uang_saku_h1_cents: ?int, uang_makan_konsumsi_cents: ?int}
     */
    public static function computeCents(SppdBudgetResult $budget, int $totalDays, array $includedComponents, array $componentOptions, TripCategory $tripCategory): array
    {
        $scale = function (Money $base, int $baseUnits, string $componentKey) use ($componentOptions, $totalDays): int {
            $option = $componentOptions[$componentKey] ?? self::defaultOption($componentKey, $totalDays);
            $perUnitCents = $base->cents / max($baseUnits, 1);

            return (int) round($perUnitCents * $option['percent'] / 100 * $option['days']);
        };

        // §III.B.3/§III.B.4 HANYA berlaku untuk Jarak Jauh (lihat
        // TripCategory::berlakuKetentuanTransitDanKonsumsiBpp()) — di luar
        // itu, meski admin sempat mencentangnya, komponen ini TIDAK PERNAH
        // dibayar (bug ditemukan lewat audit kode: sebelumnya hanya
        // disaring oleh $includedComponents, tanpa melihat kategori sama
        // sekali, menyebabkan kelebihan bayar pada trip Jarak Pendek/Luar
        // Negeri/Pindah/Detasir yang mencentang komponen ini).
        $bppTigaBBerlaku = $tripCategory->berlakuKetentuanTransitDanKonsumsiBpp();

        return [
            'uang_makan_cents' => in_array('uang_makan', $includedComponents, true) ? $scale($budget->uangMakan, $totalDays, 'uang_makan') : 0,
            'uang_saku_cents' => in_array('uang_saku', $includedComponents, true) ? $scale($budget->uangSaku, $totalDays, 'uang_saku') : 0,
            'estimasi_hotel_cents' => in_array('hotel', $includedComponents, true) && $budget->hotel !== null ? $scale($budget->hotel, $totalDays, 'hotel') : null,
            'hotel_kompensasi_cents' => in_array('hotel_kompensasi', $includedComponents, true) && $budget->hotelKompensasi !== null ? $scale($budget->hotelKompensasi, $totalDays, 'hotel_kompensasi') : null,
            'estimasi_angkutan_setempat_cents' => in_array('angkutan_setempat', $includedComponents, true) && $budget->angkutanSetempat !== null ? $scale($budget->angkutanSetempat, $totalDays, 'angkutan_setempat') : null,
            'estimasi_transportasi_tujuan_cents' => in_array('transportasi_tujuan', $includedComponents, true) && $budget->transportasiTujuan !== null ? $scale($budget->transportasiTujuan, 1, 'transportasi_tujuan') : null,
            // Komponen TERPISAH (BPP §III.B.3/§III.B.4) — memakai tarif
            // harian Uang Makan/Uang Saku yang SAMA, dijumlahkan DENGAN
            // baris uang_makan/uang_saku biasa (bukan menimpanya), lihat
            // docblock kelas ini untuk alasan lengkap.
            'uang_makan_h1_cents' => $bppTigaBBerlaku && in_array('uang_makan_h1', $includedComponents, true) ? $scale($budget->uangMakan, $totalDays, 'uang_makan_h1') : null,
            'uang_saku_h1_cents' => $bppTigaBBerlaku && in_array('uang_saku_h1', $includedComponents, true) ? $scale($budget->uangSaku, $totalDays, 'uang_saku_h1') : null,
            'uang_makan_konsumsi_cents' => $bppTigaBBerlaku && in_array('uang_makan_konsumsi', $includedComponents, true) ? $scale($budget->uangMakan, $totalDays, 'uang_makan_konsumsi') : null,
        ];
    }

    /**
     * @param  array<int, string>  $employeeIds
     * @param  array<int, string>  $includedComponents  subset of self::COMPONENT_KEYS
     * @param  array<string, array<string, array{percent: int, days: int}>>  $employeeComponentOptions  keyed employeeId => componentKey => ['percent'=>int,'days'=>int]
     * @return string id spd_memo_groups yang terbentuk
     */
    public function handle(
        array $employeeIds,
        string $memoNumber,
        DateTimeImmutable $memoDate,
        ?string $sourceDivision,
        TripCategory $tripCategory,
        string $destination,
        string $purpose,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?RadiusBand $radiusBand,
        array $includedComponents,
        array $employeeComponentOptions,
        ?string $authorizingOfficialTitle,
        ?string $authorizingOfficialName,
        ?string $lumpsumSignatory1Title,
        ?string $lumpsumSignatory1Name,
        ?string $lumpsumSignatory2Title,
        ?string $lumpsumSignatory2Name,
        string $payerScope,
        ?string $officeId,
        AuditActor $actor,
    ): string {
        if ($employeeIds === []) {
            throw new DomainException('Pilih minimal satu pegawai untuk SPPD massal ini.');
        }

        if ($includedComponents === []) {
            throw new DomainException('Pilih minimal satu komponen lumpsum yang akan diberikan.');
        }

        // "Plafon Hotel" dan "Kompensasi Tidak Ambil Fasilitas Hotel"
        // SALING MENGGANTIKAN (pegawai ambil kamar ATAU kompensasi tunai,
        // bukan dua-duanya, §II.B.6) — sebelumnya cuma diperingatkan lewat
        // teks bantuan di form, tanpa penegakan nyata, sehingga admin yang
        // mencentang keduanya membayar DUA KALI untuk satu pegawai yang
        // sama (bug ditemukan lewat audit kode).
        if (in_array('hotel', $includedComponents, true) && in_array('hotel_kompensasi', $includedComponents, true)) {
            throw new DomainException('"Plafon Hotel" dan "Kompensasi Tidak Ambil Fasilitas Hotel" saling menggantikan — pilih salah satu saja, tidak bisa dicentang berdua sekaligus.');
        }

        if ($endDate < $startDate) {
            throw new DomainException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $totalDays = $startDate->diff($endDate)->days + 1;
        $asOf = AsOfDate::on($startDate);

        return DB::transaction(function () use (
            $employeeIds, $memoNumber, $memoDate, $sourceDivision, $tripCategory, $destination,
            $purpose, $startDate, $endDate, $totalDays, $radiusBand, $includedComponents,
            $employeeComponentOptions, $authorizingOfficialTitle,
            $authorizingOfficialName, $lumpsumSignatory1Title, $lumpsumSignatory1Name,
            $lumpsumSignatory2Title, $lumpsumSignatory2Name, $payerScope, $officeId, $actor, $asOf,
        ) {
            $now = new DateTimeImmutable;
            $groupId = (string) Uuid7::generate();
            $groupNumber = $this->nextGroupNumber($now);
            $currency = $tripCategory->mataUang();

            DB::table('spd_memo_groups')->insert([
                'id' => $groupId,
                'group_number' => $groupNumber,
                'memo_number' => $memoNumber,
                'memo_date' => $memoDate->format('Y-m-d'),
                'source_division' => $sourceDivision,
                'trip_category' => $tripCategory->value,
                'radius_band' => $radiusBand?->value,
                'destination' => $destination,
                'purpose' => $purpose,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $totalDays,
                'currency' => $currency,
                'authorizing_official_title' => $authorizingOfficialTitle,
                'authorizing_official_name' => $authorizingOfficialName,
                'lumpsum_signatory_1_title' => $lumpsumSignatory1Title,
                'lumpsum_signatory_1_name' => $lumpsumSignatory1Name,
                'lumpsum_signatory_2_title' => $lumpsumSignatory2Title,
                'lumpsum_signatory_2_name' => $lumpsumSignatory2Name,
                'payer_scope' => $payerScope,
                'office_id' => $officeId,
                'created_by' => $actor->actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            foreach ($employeeIds as $employeeId) {
                $tier = null;

                if (! $tripCategory->berbasisRadius()) {
                    $tierValue = DB::table('emp_employees as e')
                        ->join('md_positions as p', 'p.id', '=', 'e.position_id')
                        ->where('e.id', $employeeId)
                        ->value('p.sppd_jabatan_tier');

                    if ($tierValue === null) {
                        throw JabatanTierNotMapped::forEmployee($employeeId);
                    }

                    $tier = JabatanTier::from($tierValue);
                }

                $budget = $this->calculator->compute($tripCategory, $tier, $radiusBand, $totalDays, $asOf);
                $componentOptions = $employeeComponentOptions[$employeeId] ?? [];
                $cents = self::computeCents($budget, $totalDays, $includedComponents, $componentOptions, $tripCategory);
                $requestId = (string) Uuid7::generate();

                // Snapshot persen+hari YANG BENAR dipakai untuk SETIAP
                // komponen (bukan cuma yang di-override admin) — computeCents()
                // sendiri jatuh ke defaultOption() untuk komponen yang tidak
                // di-override, snapshot ini WAJIB mencerminkan nilai efektif
                // yang SAMA, bukan array mentah $componentOptions yang bisa
                // parsial/kosong. Dipakai cetak Rincian Lumpsum untuk
                // merekonstruksi baris persentase resmi (lihat migrasi
                // add_component_options_snapshot_to_spd_requests) — angka
                // cents akhir saja tidak cukup untuk itu.
                $resolvedComponentOptions = [];
                foreach (self::COMPONENT_KEYS as $componentKey) {
                    $resolvedComponentOptions[$componentKey] = $componentOptions[$componentKey]
                        ?? self::defaultOption($componentKey, $totalDays);
                }

                DB::table('spd_requests')->insert([
                    'id' => $requestId,
                    'memo_group_id' => $groupId,
                    'component_options_snapshot' => json_encode($resolvedComponentOptions),
                    'request_number' => $this->nextRequestNumber($now),
                    'employee_id' => $employeeId,
                    'trip_category' => $tripCategory->value,
                    'jabatan_tier' => $tier?->value,
                    'radius_band' => $radiusBand?->value,
                    'destination' => $destination,
                    'purpose' => $purpose,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'total_days' => $totalDays,
                    'currency' => $budget->mataUang,
                    ...$cents,
                    'status' => 'approved',
                    'approver_id' => null,
                    'decided_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'version' => 1,
                ]);

                $totalAnggaranCents = $cents['uang_makan_cents']
                    + $cents['uang_saku_cents']
                    + ($cents['estimasi_hotel_cents'] ?? 0)
                    + ($cents['hotel_kompensasi_cents'] ?? 0)
                    + ($cents['estimasi_angkutan_setempat_cents'] ?? 0)
                    + ($cents['estimasi_transportasi_tujuan_cents'] ?? 0)
                    + ($cents['uang_makan_h1_cents'] ?? 0)
                    + ($cents['uang_saku_h1_cents'] ?? 0)
                    + ($cents['uang_makan_konsumsi_cents'] ?? 0);

                $this->audit->append(new AuditEntry(
                    occurredAt: $now,
                    actor: $actor,
                    auditableType: 'spd_request',
                    auditableId: $requestId,
                    action: AuditAction::Approved,
                    newValues: [
                        'trip_category' => $tripCategory->value,
                        'destination' => $destination,
                        'included_components' => $includedComponents,
                        'component_options' => $componentOptions,
                        'total_anggaran_cents' => $totalAnggaranCents,
                    ],
                    contextRef: $groupNumber,
                ));
            }

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'spd_memo_group',
                auditableId: $groupId,
                action: AuditAction::Created,
                newValues: [
                    'group_number' => $groupNumber,
                    'memo_number' => $memoNumber,
                    'jumlah_pegawai' => count($employeeIds),
                ],
            ));

            return $groupId;
        });
    }

    private function nextGroupNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('SPPD-MASSAL/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('spd_memo_groups')->where('group_number', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextRequestNumber(DateTimeImmutable $now): string
    {
        $prefix = sprintf('SPPD/%s/%s/', $now->format('Y'), $now->format('m'));

        $count = DB::table('spd_requests')->where('request_number', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
