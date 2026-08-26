<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tarif SPPD — BPP/442/03/64/2026 (30 Januari 2026), ditranskripsi dari
 * berkas PDF resmi yang dibagikan (bukan gambar terpisah) — jauh lebih
 * dapat diandalkan dibanding transkripsi Lampiran II BPP Penggajian.
 *
 * CAKUPAN SENGAJA DIPERSEMPIT (dicatat, bukan diam-diam):
 *  - Tabel Pengurus (Direktur/Komisaris/DPS) TIDAK disemai — tidak ada
 *    data pegawai berlevel itu di Wave 1.
 *  - Plafon hotel Dalam NTB: kolom Lombok dan Sumbawa pada BPP bernilai
 *    IDENTIK untuk setiap jenjang — digabung jadi satu baris tanpa
 *    kehilangan informasi (bukan penyederhanaan angka, murni dedup).
 *  - Luar Negeri/Pindah/Detasir TIDAK punya plafon hotel/angkutan resmi
 *    pada BPP ini — hanya Uang Makan/Uang Saku yang disemai untuk
 *    kategori tersebut.
 */
return new class extends Migration
{
    private const SOURCE = 'BPP/442/03/64/2026';

    private const EFFECTIVE_FROM = '2026-01-30';

    /** [tier => [makan, saku]] dalam Rupiah, kecuali disebutkan lain. */
    private const KELUAR_PROVINSI = [
        'sevp' => [400_000, 650_000],
        'pejabat_eksekutif' => [350_000, 550_000],
        'dept_head_sbm_mgr' => [300_000, 500_000],
        'team_leader_spv_officer_staff' => [250_000, 450_000],
        'non_staff' => [200_000, 400_000],
    ];

    private const DALAM_PROVINSI = [
        'sevp' => [200_000, 400_000],
        'pejabat_eksekutif' => [200_000, 300_000],
        'dept_head_sbm_mgr' => [150_000, 200_000],
        'team_leader_spv_officer_staff' => [150_000, 200_000],
        'non_staff' => [150_000, 200_000],
    ];

    /** Dalam USD — luar_negeri. */
    private const LUAR_NEGERI = [
        'sevp' => [150, 500],
        'pejabat_eksekutif' => [150, 450],
        'dept_head_sbm_mgr' => [150, 400],
        'team_leader_spv_officer_staff' => [150, 400],
        'non_staff' => [100, 350],
    ];

    /** Tabel BPP TIDAK punya baris Non Staff untuk Pindah/Detasir — sengaja tidak ada di sini juga. */
    private const PINDAH_DETASIR = [
        'sevp' => [250_000, 350_000],
        'pejabat_eksekutif' => [225_000, 275_000],
        'dept_head_sbm_mgr' => [200_000, 250_000],
        'team_leader_spv_officer_staff' => [200_000, 225_000],
    ];

    /** [tier => [luar_ntb, dalam_ntb]]. */
    private const HOTEL_CAP = [
        'sevp' => [2_500_000, 1_000_000],
        'pejabat_eksekutif' => [1_750_000, 800_000],
        'dept_head_sbm_mgr' => [1_500_000, 600_000],
        'team_leader_spv_officer_staff' => [1_000_000, 550_000],
        'non_staff' => [950_000, 500_000],
    ];

    /** [tier => [keluar, dalam]]. */
    private const HOTEL_COMPENSATION = [
        'sevp' => [500_000, 250_000],
        'pejabat_eksekutif' => [350_000, 200_000],
        'dept_head_sbm_mgr' => [250_000, 150_000],
        'team_leader_spv_officer_staff' => [200_000, 100_000],
        'non_staff' => [150_000, 75_000],
    ];

    private const LOCAL_TRANSPORT_CAP = [
        'sevp' => [500_000, 300_000],
        'pejabat_eksekutif' => [250_000, 150_000],
        'dept_head_sbm_mgr' => [250_000, 150_000],
        'team_leader_spv_officer_staff' => [250_000, 150_000],
        'non_staff' => [250_000, 150_000],
    ];

    private const DESTINATION_TRANSPORT_CAP = [
        'sevp' => [1_000_000, 300_000],
        'pejabat_eksekutif' => [750_000, 150_000],
        'dept_head_sbm_mgr' => [750_000, 150_000],
        'team_leader_spv_officer_staff' => [750_000, 150_000],
        'non_staff' => [750_000, 150_000],
    ];

    /** Uang makan Jarak Pendek — per pita radius, BUKAN per jenjang (§III.A.1). */
    private const JARAK_PENDEK_UANG_MAKAN = [
        '30_100' => 50_000,
        '100_150' => 100_000,
        '150_plus' => 150_000,
    ];

    private const POSITION_TIER_MAP = [
        'DIV_HEAD' => 'pejabat_eksekutif',
        'BM' => 'pejabat_eksekutif',
        'DEPT_HEAD' => 'dept_head_sbm_mgr',
        'SBM' => 'dept_head_sbm_mgr',
        'MGR' => 'dept_head_sbm_mgr',
        'SPV' => 'team_leader_spv_officer_staff',
        'OFC' => 'team_leader_spv_officer_staff',
        'CS' => 'team_leader_spv_officer_staff',
        'TELLER' => 'team_leader_spv_officer_staff',
        'ADM' => 'team_leader_spv_officer_staff',
        'SATPAM' => 'non_staff',
    ];

    public function up(): void
    {
        foreach (self::POSITION_TIER_MAP as $positionCode => $tier) {
            DB::table('md_positions')->where('code', $positionCode)->update(['sppd_jabatan_tier' => $tier]);
        }

        foreach (self::JARAK_PENDEK_UANG_MAKAN as $band => $amount) {
            $this->insert('uang_makan', 'jarak_pendek', null, $band, 'IDR', $amount);
        }

        foreach (['jarak_jauh_keluar_provinsi' => self::KELUAR_PROVINSI, 'jarak_jauh_dalam_provinsi' => self::DALAM_PROVINSI] as $category => $rows) {
            foreach ($rows as $tier => [$makan, $saku]) {
                $this->insert('uang_makan', $category, $tier, null, 'IDR', $makan);
                $this->insert('uang_saku', $category, $tier, null, 'IDR', $saku);
            }
        }

        foreach (self::LUAR_NEGERI as $tier => [$makan, $saku]) {
            $this->insert('uang_makan', 'luar_negeri', $tier, null, 'USD', $makan);
            $this->insert('uang_saku', 'luar_negeri', $tier, null, 'USD', $saku);
        }

        foreach (['pindah', 'detasir'] as $category) {
            foreach (self::PINDAH_DETASIR as $tier => [$makan, $saku]) {
                $this->insert('uang_makan', $category, $tier, null, 'IDR', $makan);
                $this->insert('uang_saku', $category, $tier, null, 'IDR', $saku);
            }
        }

        foreach (self::HOTEL_CAP as $tier => [$luarNtb, $dalamNtb]) {
            $this->insert('hotel_cap', 'jarak_jauh_keluar_provinsi', $tier, null, 'IDR', $luarNtb);
            $this->insert('hotel_cap', 'jarak_jauh_dalam_provinsi', $tier, null, 'IDR', $dalamNtb);
        }

        foreach (self::HOTEL_COMPENSATION as $tier => [$keluar, $dalam]) {
            $this->insert('hotel_compensation', 'jarak_jauh_keluar_provinsi', $tier, null, 'IDR', $keluar);
            $this->insert('hotel_compensation', 'jarak_jauh_dalam_provinsi', $tier, null, 'IDR', $dalam);
        }

        foreach (self::LOCAL_TRANSPORT_CAP as $tier => [$keluar, $dalam]) {
            $this->insert('local_transport_cap', 'jarak_jauh_keluar_provinsi', $tier, null, 'IDR', $keluar);
            $this->insert('local_transport_cap', 'jarak_jauh_dalam_provinsi', $tier, null, 'IDR', $dalam);
        }

        foreach (self::DESTINATION_TRANSPORT_CAP as $tier => [$keluar, $dalam]) {
            $this->insert('destination_transport_cap', 'jarak_jauh_keluar_provinsi', $tier, null, 'IDR', $keluar);
            $this->insert('destination_transport_cap', 'jarak_jauh_dalam_provinsi', $tier, null, 'IDR', $dalam);
        }
    }

    public function down(): void
    {
        DB::table('pay_sppd_tariffs')->where('source_document', self::SOURCE)->delete();

        DB::table('md_positions')->whereIn('code', array_keys(self::POSITION_TIER_MAP))->update(['sppd_jabatan_tier' => null]);
    }

    private function insert(string $component, string $category, ?string $tier, ?string $radiusBand, string $currency, int $amount): void
    {
        DB::table('pay_sppd_tariffs')->insert([
            'id' => (string) Str::uuid(),
            'component' => $component,
            'trip_category' => $category,
            'jabatan_tier' => $tier,
            'radius_band' => $radiusBand,
            'currency' => $currency,
            'amount_cents' => $amount * 100,
            'effective_from' => self::EFFECTIVE_FROM,
            'effective_to' => null,
            'source_document' => self::SOURCE,
            'created_at' => now(),
            'updated_at' => now(),
            'version' => 1,
        ]);
    }
};
