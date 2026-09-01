<?php

declare(strict_types=1);

namespace Tests\Feature\Sppd;

use App\Models\User;
use App\Modules\Sppd\Application\SubmitSppdMemoGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SPPD Massal — input berbasis memo divisi (Admin HC bank-wide / Admin
 * Cabang kantornya sendiri), langsung auto-approved (approver_id=NULL),
 * TERPISAH dari alur mandiri (SubmitSppdRequestTest/SppdDisbursementTest/
 * SppdApprovalScopeTest, tidak disentuh sama sekali oleh fitur ini).
 *
 * Data contoh (2026_01_01_000007_seed_sample_data.php): Siti Rahmawati
 * (2018.03.0142, OFC, KC-MTR), Ahmad Fauzi (2015.07.0088, BM, KC-MTR —
 * kantor SAMA dengan Siti), Budi Santoso (2020.01.0231, TELLER, KCP-PRY),
 * Nur Aisyah (2014.02.0061, DIV_HEAD, KP, hr_approver + pimpinan_kantor).
 */
final class SppdMemoSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_hc_input_memo_massal_bank_wide_menghasilkan_baris_auto_approved(): void
    {
        $sitiId = $this->employeeId('2018.03.0142'); // KC-MTR
        $budiId = $this->employeeId('2020.01.0231'); // KCP-PRY — kantor BEDA, membuktikan bank-wide

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId, $budiId],
            'memo_number' => 'MEMO/HC/2026/09/001',
            'memo_date' => '2026-09-01',
            'source_division' => 'Human Capital',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Rapat koordinasi tahunan',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'included_components' => self::allComponentsExceptHotelKompensasi(),
            'authorizing_official_title' => 'Direktur Utama',
            'authorizing_official_name' => 'Contoh Nama',
            'lumpsum_signatory_1_title' => 'Pimpinan HC',
            'lumpsum_signatory_1_name' => 'Contoh Pimpinan',
            'lumpsum_signatory_2_title' => 'Dept Head HC',
            'lumpsum_signatory_2_name' => 'Contoh Dept Head',
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $group = DB::table('spd_memo_groups')->where('id', $groupId)->first();
        $this->assertNotNull($group);
        $this->assertStringStartsWith('SPPD-MASSAL/', $group->group_number);
        $this->assertSame('hc', $group->payer_scope);
        $this->assertNull($group->office_id);

        foreach ([$sitiId, $budiId] as $employeeId) {
            $row = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $employeeId)->first();
            $this->assertNotNull($row);
            $this->assertSame('approved', $row->status);
            $this->assertNull($row->approver_id);
            $this->assertNotNull($row->decided_at);
            // team_leader_spv_officer_staff (OFC/TELLER) — 2 hari.
            $this->assertSame(500_000_00, $row->uang_makan_cents);
            $this->assertSame(900_000_00, $row->uang_saku_cents);
        }
    }

    public function test_persen_dan_hari_bisa_berbeda_per_pegawai_untuk_komponen_yang_sama(): void
    {
        $sitiId = $this->employeeId('2018.03.0142'); // team_leader — dibiarkan bawaan (100% x 3 hari)
        $budiId = $this->employeeId('2020.01.0231'); // team_leader juga — di-override 25% x 1 hari (H-1/H+1)

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId, $budiId],
            'memo_number' => 'MEMO/HC/2026/09/005',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji persen/hari per pegawai',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12', // 3 hari
            'included_components' => self::allComponentsExceptHotelKompensasi(),
            'employee_options' => [
                $budiId => [
                    'uang_makan' => ['percent' => 25, 'days' => 1],
                ],
            ],
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $siti = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $sitiId)->first();
        $budi = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $budiId)->first();

        // Siti: bawaan penuh — 3 x Rp250.000 = Rp750.000.
        $this->assertSame(750_000_00, $siti->uang_makan_cents);
        // Budi: di-override 25% x 1 hari dari tarif harian yang SAMA (Rp250.000/hari) = Rp62.500.
        $this->assertSame(62_500_00, $budi->uang_makan_cents);
        // Komponen LAIN yang tidak di-override tetap bawaan penuh untuk Budi juga.
        $this->assertSame(1_350_000_00, $budi->uang_saku_cents); // 3 x Rp450.000
        $this->assertSame($siti->uang_saku_cents, $budi->uang_saku_cents);
    }

    public function test_komponen_h1_dijumlahkan_dengan_uang_makan_saku_biasa_bukan_menimpa(): void
    {
        $sitiId = $this->employeeId('2018.03.0142'); // team_leader

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/007',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji komponen H-1/H+1 sebagai baris terpisah',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12', // 3 hari total: 2 hari normal + 1 hari transit
            'included_components' => ['uang_makan', 'uang_saku', 'uang_makan_h1', 'uang_saku_h1'],
            'employee_options' => [
                // Uang Makan/Saku BIASA sengaja diturunkan ke 2 hari (bukan 3) —
                // hari transit-nya dipindah ke baris uang_makan_h1/uang_saku_h1 (bawaan 25% x 1 hari).
                $sitiId => [
                    'uang_makan' => ['percent' => 100, 'days' => 2],
                    'uang_saku' => ['percent' => 100, 'days' => 2],
                ],
            ],
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $row = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $sitiId)->first();

        $this->assertSame(500_000_00, $row->uang_makan_cents); // 100% x 2 x Rp250.000 — TIDAK tertimpa
        $this->assertSame(62_500_00, $row->uang_makan_h1_cents); // bawaan 25% x 1 x Rp250.000
        $this->assertSame(900_000_00, $row->uang_saku_cents); // 100% x 2 x Rp450.000 — TIDAK tertimpa
        $this->assertSame(112_500_00, $row->uang_saku_h1_cents); // bawaan 25% x 1 x Rp450.000

        // Total makan seharusnya penjumlahan KEDUANYA (bukti tidak saling menimpa).
        $totalMakan = $row->uang_makan_cents + $row->uang_makan_h1_cents;
        $this->assertSame(562_500_00, $totalMakan);
    }

    public function test_uang_makan_konsumsi_sebagian_bisa_dicentang_tanpa_uang_makan_biasa(): void
    {
        $sitiId = $this->employeeId('2018.03.0142'); // team_leader

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/008',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji komponen konsumsi ditanggung sebagian panitia',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11', // 2 hari
            'included_components' => ['uang_makan_konsumsi'], // TANPA "uang_makan" biasa
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $row = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $sitiId)->first();

        $this->assertSame(0, $row->uang_makan_cents); // "Uang Makan" biasa tidak dicentang — 0, bukan dipakai
        // bawaan 70% x 2 hari x Rp250.000/hari = Rp350.000 (§III.B.4.c — panitia menanggung 1x makan).
        $this->assertSame(350_000_00, $row->uang_makan_konsumsi_cents);
        $this->assertSame(0, $row->uang_saku_cents); // tidak dicentang — TIDAK ADA aturan setara untuk Uang Saku
    }

    public function test_admin_hc_bisa_memilih_sebagian_komponen_lumpsum_saja(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/003',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji komponen sebagian',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'included_components' => ['uang_saku'], // HANYA uang saku — sisanya harus 0/null
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $row = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $sitiId)->first();
        $this->assertSame(0, $row->uang_makan_cents);
        $this->assertSame(900_000_00, $row->uang_saku_cents); // tetap dihitung sesuai jenis SPPD+jenjang
        $this->assertNull($row->estimasi_hotel_cents);
        $this->assertNull($row->hotel_kompensasi_cents);
        $this->assertNull($row->estimasi_angkutan_setempat_cents);
        $this->assertNull($row->estimasi_transportasi_tujuan_cents);
    }

    public function test_admin_hc_bisa_mencentang_kompensasi_tidak_ambil_hotel(): void
    {
        $sitiId = $this->employeeId('2018.03.0142'); // team_leader

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/006',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji kompensasi tidak ambil hotel',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11', // 2 hari
            'included_components' => ['uang_makan', 'hotel_kompensasi'], // pegawai TIDAK ambil kamar hotel
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $row = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $sitiId)->first();
        $this->assertNull($row->estimasi_hotel_cents); // Plafon Hotel TIDAK dicentang
        $this->assertSame(400_000_00, $row->hotel_kompensasi_cents); // 2 x Rp200.000/hari (team_leader, keluar provinsi)
    }

    public function test_menyimpan_tanpa_komponen_lumpsum_tercentang_ditolak(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/004',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji tanpa komponen',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'included_components' => [],
        ]);

        $response->assertSessionHasErrors('included_components');
        $this->assertSame(0, DB::table('spd_memo_groups')->count());
    }

    public function test_pratinjau_mencerminkan_persen_hari_override_per_pegawai(): void
    {
        $budiId = $this->employeeId('2020.01.0231');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get(route('sppd-memo.create', [
            'employee_ids' => [$budiId],
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12', // 3 hari, bawaan makan = Rp750.000
            'included_components' => self::allComponentsExceptHotelKompensasi(),
            'employee_options' => [
                $budiId => ['uang_makan' => ['percent' => 25, 'days' => 1]],
            ],
        ]));

        $response->assertOk();
        $response->assertSeeText('Rp62.500'); // 25% x 1 hari x Rp250.000 — bukan lagi Rp750.000 (bawaan 3 hari penuh)
    }

    public function test_pratinjau_lumpsum_mencerminkan_komponen_yang_dicentang(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get(route('sppd-memo.create', [
            'employee_ids' => [$sitiId],
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'included_components' => ['uang_saku'], // makan SENGAJA tidak dicentang
        ]));

        $response->assertOk();
        $response->assertSeeText('Rp900.000'); // uang saku tetap tampil
        $response->assertDontSeeText('Rp500.000'); // uang makan (dikecualikan) tidak lagi tampil sebagai angka
    }

    public function test_pratinjau_lumpsum_di_form_buat_sesuai_jenis_sppd_dan_jenjang_masing_masing(): void
    {
        $sitiId = $this->employeeId('2018.03.0142'); // OFC → team_leader_spv_officer_staff
        $ahmadId = $this->employeeId('2015.07.0088'); // BM → pejabat_eksekutif

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get(route('sppd-memo.create', [
            'employee_ids' => [$sitiId, $ahmadId],
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11', // 2 hari
            'included_components' => self::allComponentsExceptHotelKompensasi(),
        ]));

        $response->assertOk();
        // Siti (team_leader): 2x250.000 makan; Ahmad (pejabat_eksekutif): 2x350.000 makan —
        // dua jenjang BEDA harus menghasilkan angka BEDA untuk kategori yang SAMA.
        $response->assertSeeText('Rp500.000');
        $response->assertSeeText('Rp700.000');
    }

    public function test_pratinjau_lumpsum_jarak_pendek_hanya_uang_makan_tanpa_saku_hotel_angkutan(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get(route('sppd-memo.create', [
            'employee_ids' => [$sitiId],
            'trip_category' => 'jarak_pendek',
            'radius_band' => '30_100',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10', // 1 hari
            'included_components' => self::allComponentsExceptHotelKompensasi(),
        ]));

        $response->assertOk();
        $response->assertSeeText('Rp50.000'); // 1 x tarif pita 30-100km
    }

    /**
     * Regresi (bug ditemukan lewat audit kode): computeCents() sebelumnya
     * hanya menyaring lewat $includedComponents, tanpa melihat kategori
     * perjalanan sama sekali — komponen §III.B.3/§III.B.4 (H-1/H+1,
     * konsumsi ditanggung panitia) HANYA berlaku untuk Jarak Jauh
     * (TripCategory::berlakuKetentuanTransitDanKonsumsiBpp()), sehingga
     * mencentangnya pada Jarak Pendek/Luar Negeri/Pindah/Detasir
     * seharusnya TIDAK PERNAH membayar apa pun.
     */
    public function test_komponen_h1_dan_konsumsi_tidak_dibayar_untuk_jarak_pendek_meski_dicentang(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/'.uniqid(),
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_pendek',
            'radius_band' => '30_100',
            'destination' => 'Mataram',
            'purpose' => 'Uji',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'included_components' => ['uang_makan', 'uang_makan_h1', 'uang_saku_h1', 'uang_makan_konsumsi'],
        ]);

        $response->assertSessionHas('sukses');
        $groupId = $this->groupIdFromRedirect($response);

        $request = DB::table('spd_requests')->where('memo_group_id', $groupId)->where('employee_id', $sitiId)->first();
        $this->assertSame(50_000_00, $request->uang_makan_cents); // 1 x tarif pita 30-100km — komponen yang MEMANG berlaku
        $this->assertNull($request->uang_makan_h1_cents, 'Tidak berlaku untuk Jarak Pendek meski dicentang.');
        $this->assertNull($request->uang_saku_h1_cents, 'Tidak berlaku untuk Jarak Pendek meski dicentang.');
        $this->assertNull($request->uang_makan_konsumsi_cents, 'Tidak berlaku untuk Jarak Pendek meski dicentang.');
    }

    /**
     * Regresi (bug ditemukan lewat audit kode): "Plafon Hotel" dan
     * "Kompensasi Tidak Ambil Fasilitas Hotel" saling menggantikan
     * (§II.B.6) — sebelumnya cuma diperingatkan lewat teks bantuan form
     * tanpa penegakan nyata, sehingga admin yang mencentang keduanya
     * membayar dua kali untuk satu pegawai yang sama.
     */
    public function test_hotel_dan_kompensasi_hotel_tidak_bisa_dicentang_berdua_sekaligus(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId],
            'memo_number' => 'MEMO/HC/2026/09/'.uniqid(),
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'included_components' => ['uang_makan', 'hotel', 'hotel_kompensasi'],
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('spd_memo_groups')->where('destination', 'Surabaya')->count());
    }

    public function test_pratinjau_menampilkan_pesan_gagal_jika_jenjang_belum_dipetakan(): void
    {
        DB::table('md_positions')->where('code', 'TELLER')->update(['sppd_jabatan_tier' => null]);
        $budiId = $this->employeeId('2020.01.0231'); // TELLER — tier baru saja dicabut

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->get(route('sppd-memo.create', [
            'employee_ids' => [$budiId],
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]));

        $response->assertOk();
        $response->assertSeeText('belum dipetakan');
    }

    public function test_admin_cabang_input_memo_massal_hanya_pegawai_kantornya(): void
    {
        $this->grantHrAdminTo('2015.07.0088'); // Ahmad Fauzi, KC-MTR

        $sitiId = $this->employeeId('2018.03.0142'); // KC-MTR — dalam lingkup
        $budiId = $this->employeeId('2020.01.0231'); // KCP-PRY — LUAR lingkup

        $response = $this->actingAs($this->userWithNrp('2015.07.0088'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId, $budiId],
            'memo_number' => 'MEMO/CAB/2026/09/001',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_dalam_provinsi',
            'destination' => 'Mataram',
            'purpose' => 'Pelatihan',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'included_components' => self::allComponentsExceptHotelKompensasi(),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, DB::table('spd_memo_groups')->count());
    }

    public function test_memo_massal_baris_gagal_satu_pegawai_membatalkan_seluruh_grup(): void
    {
        $sitiId = $this->employeeId('2018.03.0142');
        $budiId = $this->employeeId('2020.01.0231');

        // Cabut pemetaan tier jabatan TELLER (posisi Budi) — memaksa
        // JabatanTierNotMapped di tengah loop, SETELAH Siti (baris
        // pertama) sudah "dihitung" dalam transaksi yang sama.
        DB::table('md_positions')->where('code', 'TELLER')->update(['sppd_jabatan_tier' => null]);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => [$sitiId, $budiId],
            'memo_number' => 'MEMO/HC/2026/09/002',
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Bali',
            'purpose' => 'Studi banding',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
            'included_components' => self::allComponentsExceptHotelKompensasi(),
        ]);

        $response->assertSessionHas('gagal');
        $this->assertSame(0, DB::table('spd_memo_groups')->where('destination', 'Bali')->count());
        $this->assertSame(0, DB::table('spd_requests')->where('employee_id', $sitiId)->where('destination', 'Bali')->count());
        $this->assertSame(0, DB::table('spd_requests')->where('employee_id', $budiId)->where('destination', 'Bali')->count());
    }

    public function test_baris_memo_tidak_muncul_di_antrean_persetujuan_maupun_pencairan_satuan(): void
    {
        $groupId = $this->submitMemoGroup(['2018.03.0142', '2020.01.0231']);
        $this->assertNotEmpty($groupId);

        $nurAisyah = $this->userWithNrp('2014.02.0061'); // hr_approver + pimpinan_kantor

        $approvalQueue = $this->actingAs($nurAisyah)->get('/persetujuan/sppd');
        $approvalQueue->assertOk();
        $approvalQueue->assertDontSeeText('Siti Rahmawati');
        $approvalQueue->assertDontSeeText('Budi Santoso');

        $disbursementQueue = $this->actingAs($nurAisyah)->get('/persetujuan/sppd-pencairan');
        $disbursementQueue->assertOk();
        $disbursementQueue->assertDontSeeText('Siti Rahmawati');
        $disbursementQueue->assertDontSeeText('Budi Santoso');
    }

    public function test_cetak_surat_jalan_dan_rincian_lumpsum(): void
    {
        $groupId = $this->submitMemoGroup(['2018.03.0142', '2020.01.0231']);
        $nurAisyah = $this->userWithNrp('2014.02.0061');

        $suratJalan = $this->actingAs($nurAisyah)->get(route('sppd-memo.print-surat-jalan', $groupId));
        $suratJalan->assertOk();
        $this->assertSame('application/pdf', $suratJalan->headers->get('Content-Type'));

        $requestIds = DB::table('spd_requests')->where('memo_group_id', $groupId)->pluck('id');
        $this->assertCount(2, $requestIds);

        foreach ($requestIds as $requestId) {
            $rincian = $this->actingAs($nurAisyah)->get(route('sppd-memo.print-rincian-lumpsum', [$groupId, $requestId]));
            $rincian->assertOk();
            $this->assertSame('application/pdf', $rincian->headers->get('Content-Type'));
        }

        // Pasangan grup/permintaan yang tidak cocok — 404.
        $otherGroupId = $this->submitMemoGroup(['2015.07.0088']);
        $this->actingAs($nurAisyah)
            ->get(route('sppd-memo.print-rincian-lumpsum', [$otherGroupId, $requestIds->first()]))
            ->assertNotFound();
    }

    public function test_admin_cabang_tidak_bisa_melihat_memo_grup_kantor_lain(): void
    {
        $groupId = $this->submitMemoGroup(['2018.03.0142']); // grup HC (bank-wide)

        $rina = $this->userWithNrp('2021.05.0302'); // hr_admin, KCP-GRG — BUKAN hr_approver

        $this->actingAs($rina)->get(route('sppd-memo.show', $groupId))->assertNotFound();
        $this->actingAs($rina)->get(route('sppd-memo.print-surat-jalan', $groupId))->assertNotFound();
    }

    /** @param array<int, string> $nrps */
    private function submitMemoGroup(array $nrps): string
    {
        $employeeIds = array_map(fn (string $nrp) => $this->employeeId($nrp), $nrps);

        $response = $this->actingAs($this->userWithNrp('2014.02.0061'))->post(route('sppd-memo.store'), [
            'employee_ids' => $employeeIds,
            'memo_number' => 'MEMO/HC/2026/09/'.uniqid(),
            'memo_date' => '2026-09-01',
            'trip_category' => 'jarak_jauh_keluar_provinsi',
            'destination' => 'Surabaya',
            'purpose' => 'Uji',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'included_components' => self::allComponentsExceptHotelKompensasi(),
        ]);

        $response->assertSessionHas('sukses');

        return $this->groupIdFromRedirect($response);
    }

    private function groupIdFromRedirect(TestResponse $response): string
    {
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        preg_match('~/sppd-massal/([0-9a-f-]{36})~', (string) $location, $m);
        $this->assertNotEmpty($m, "Tidak menemukan group id di redirect: {$location}");

        return $m[1];
    }

    private function grantHrAdminTo(string $nrp): void
    {
        DB::table('model_has_roles')->insert([
            'role_id' => DB::table('roles')->where('name', 'hr_admin')->value('id'),
            'model_type' => User::class,
            'model_id' => $this->userWithNrp($nrp)->id,
        ]);
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        $employeeId = $this->employeeId($nrp);

        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }

    /**
     * "hotel" dan "hotel_kompensasi" SALING MENGGANTIKAN (§II.B.6,
     * ditegakkan SubmitSppdMemoGroup::handle()) — tidak bisa dicentang
     * berdua sekaligus. Tes-tes di file ini yang ingin "semua komponen
     * yang berlaku" (bukan menguji hotel_kompensasi itu sendiri, lihat
     * test_admin_hc_bisa_mencentang_kompensasi_tidak_ambil_hotel untuk
     * itu) memakai daftar ini, bukan SubmitSppdMemoGroup::COMPONENT_KEYS
     * mentah.
     *
     * @return array<int, string>
     */
    private static function allComponentsExceptHotelKompensasi(): array
    {
        return array_values(array_diff(SubmitSppdMemoGroup::COMPONENT_KEYS, ['hotel_kompensasi']));
    }
}
