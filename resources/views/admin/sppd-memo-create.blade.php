@extends('layouts.app')

@section('judul', 'Input SPPD Massal')
@section('peran', $bankWide ? 'Admin HC' : 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.blok-kondisional{display:none}
.blok-kondisional.tampil{display:block}
.picker{border:1px solid var(--garis);border-radius:var(--r);overflow:hidden}
.picker-alat{display:flex;gap:10px;align-items:center;padding:10px 12px;
  background:var(--latar);border-bottom:1px solid var(--garis)}
.picker-alat input[type=text]{flex:1;width:auto;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.picker-alat label{display:flex;align-items:center;gap:6px;font-size:12px;white-space:nowrap}
.picker-daftar{max-height:340px;overflow-y:auto}
.picker-baris{display:flex;align-items:center;gap:10px;padding:9px 12px;
  border-bottom:1px solid var(--garis);font-size:12.5px}
.picker-baris:last-child{border-bottom:0}
.picker-baris small{display:block;color:var(--teks-lemah);font-size:11px;margin-top:1px}
.picker-hitung{padding:8px 12px;font-size:11.5px;color:var(--teks-lemah);background:var(--latar);
  border-top:1px solid var(--garis)}
.picker input[type=checkbox]{width:16px;height:16px;padding:0;flex-shrink:0;accent-color:var(--hijau)}
.subjudul{font-size:12px;font-weight:700;margin:16px 0 8px;color:var(--teks-lemah);text-transform:uppercase;letter-spacing:.05em}
.komponen-list{margin-bottom:16px}
.komponen-list label.baris-komponen{display:flex;align-items:center;gap:8px;font-size:12.5px;padding:5px 0;cursor:pointer}
.komponen-list input[type=checkbox]{width:16px;height:16px;accent-color:var(--hijau)}
.pratinjau{background:var(--hijau-tua);color:#fff;border-radius:var(--r);padding:18px;margin-bottom:16px}
.pratinjau .judul{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.75;margin-bottom:13px}
.pratinjau table{width:100%;border-collapse:collapse;font-size:13.5px}
.pratinjau th{text-align:center;font-weight:600;opacity:.75;padding:8px 14px;font-size:11.5px;
  text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
.pratinjau th:first-child,.pratinjau td:first-child{text-align:left}
.pratinjau td{text-align:center;padding:14px;border-top:1px solid rgba(255,255,255,.14);white-space:nowrap}
.pratinjau tfoot td{font-weight:700;border-top:2px solid rgba(255,255,255,.4);text-align:right}
.pratinjau tfoot td:first-child{text-align:left}
.pratinjau .ket{font-size:11px;opacity:.7;margin-top:12px;line-height:1.7}
.pratinjau .opsi-komponen{display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;margin-bottom:6px}
.pratinjau .subtotal{margin-top:4px;font-size:14.5px;font-weight:600}
.pratinjau input[type=number]{padding:8px 6px;border-radius:7px;border:1.5px solid rgba(255,255,255,.4);
  background:rgba(255,255,255,.14);color:#fff;font-size:14px;text-align:center;font-family:inherit}
.pratinjau input[type=number]:focus{outline:none;border-color:#fff;background:rgba(255,255,255,.22)}
.pratinjau input.sel-persen{width:64px}
.pratinjau input.sel-hari{width:56px}
.pratinjau-gulir{overflow-x:auto}
.pratinjau td:not(:first-child),.pratinjau th:not(:first-child){min-width:150px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Input SPPD Massal</h2>
  <p>Satu memo, banyak pegawai — langsung berstatus disetujui begitu disimpan.</p>
</div>

@if ($errors->any())
  <div class="pesan gagal">{{ $errors->first() }}</div>
@endif
@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

@if ($previewError)
  <div class="pesan gagal">{{ $previewError }}</div>
@endif

<div class="kartu">
  <div class="kartu-judul">Detail Memo & Perjalanan</div>

  <form method="POST" action="{{ route('sppd-memo.store') }}" id="form-sppd-memo">
    @csrf

    @php
      $isi = fn ($nama) => old($nama, request()->query($nama));
      $dipilih = (array) ($isi('employee_ids') ?? []);
    @endphp

    <div class="baris-bidang">
      <div class="bidang">
        <label>Nomor Memo</label>
        <input type="text" name="memo_number" maxlength="100" value="{{ $isi('memo_number') }}" required>
      </div>
      <div class="bidang">
        <label>Tanggal Memo</label>
        <input type="date" name="memo_date" value="{{ $isi('memo_date') }}" required>
      </div>
      <div class="bidang">
        <label>Divisi Asal (opsional — Kantor Pusat)</label>
        <select name="source_division">
          <option value="">— Pilih —</option>
          @foreach ($divisions as $d)
            <option value="{{ $d }}" @selected($isi('source_division') === $d)>{{ $d }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label>Kategori Perjalanan</label>
        <select name="trip_category" id="trip_category" required onchange="perbaruiBlokKondisional()">
          <option value="" disabled @selected(! $isi('trip_category'))>Pilih kategori…</option>
          @foreach ($tripCategories as $c)
            <option value="{{ $c->value }}" @selected($isi('trip_category') === $c->value)>{{ $c->label() }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang blok-kondisional" data-untuk="jarak_pendek" id="blok-radius">
        <label for="radius_band">Pita Jarak Tempuh</label>
        <select id="radius_band" name="radius_band">
          <option value="" disabled @selected(! $isi('radius_band'))>Pilih pita jarak…</option>
          @foreach ($radiusBands as $b)
            <option value="{{ $b->value }}" @selected($isi('radius_band') === $b->value)>{{ $b->label() }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="bidang">
      <label>Tujuan</label>
      <input type="text" name="destination" maxlength="200" value="{{ $isi('destination') }}" required>
    </div>
    <div class="bidang">
      <label>Keperluan</label>
      <textarea name="purpose" rows="2" required maxlength="2000">{{ $isi('purpose') }}</textarea>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label>Tanggal Mulai</label>
        <input type="date" name="start_date" value="{{ $isi('start_date') }}" required>
      </div>
      <div class="bidang">
        <label>Tanggal Selesai</label>
        <input type="date" name="end_date" value="{{ $isi('end_date') }}" required>
      </div>
    </div>

    <div class="subjudul">Komponen Lumpsum yang Diberikan</div>
    <div class="komponen-list">
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="uang_makan" @checked(in_array('uang_makan', $includedComponents, true))>
        Uang Makan
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="uang_saku" @checked(in_array('uang_saku', $includedComponents, true))>
        Uang Saku
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="hotel" @checked(in_array('hotel', $includedComponents, true))>
        Plafon Hotel
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="hotel_kompensasi" @checked(in_array('hotel_kompensasi', $includedComponents, true))>
        Kompensasi Tidak Ambil Fasilitas Hotel
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="angkutan_setempat" @checked(in_array('angkutan_setempat', $includedComponents, true))>
        Plafon Angkutan Setempat
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="transportasi_tujuan" @checked(in_array('transportasi_tujuan', $includedComponents, true))>
        Plafon Transportasi ke Tujuan
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="uang_makan_h1" @checked(in_array('uang_makan_h1', $includedComponents, true))>
        Uang Makan — Hari Transit H-1/H+1 (§III.B.3, 25%)
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="uang_saku_h1" @checked(in_array('uang_saku_h1', $includedComponents, true))>
        Uang Saku — Hari Transit H-1/H+1 (§III.B.3, 25%)
      </label>
      <label class="baris-komponen">
        <input type="checkbox" name="included_components[]" value="uang_makan_konsumsi" @checked(in_array('uang_makan_konsumsi', $includedComponents, true))>
        Uang Makan — Konsumsi Ditanggung Sebagian Panitia (§III.B.4, 70%/30%)
      </label>
    </div>
    <p style="font-size:11px;color:var(--teks-lemah);margin:-8px 0 16px">
      Komponen yang tidak berlaku untuk kategori perjalanan yang dipilih (mis. Uang Saku untuk Jarak
      Pendek, atau Hotel/Angkutan/Transportasi/Kompensasi Hotel di luar Jarak Jauh) tetap Rp0 sesuai
      BPP walau dicentang — centang di sini hanya menentukan komponen mana yang DIBERIKAN dari yang
      MEMANG berlaku, bukan menambah komponen baru di luar aturan BPP. "Plafon Hotel" dan
      "Kompensasi Tidak Ambil Fasilitas Hotel" secara bisnis SALING MENGGANTIKAN (pegawai ambil
      kamar ATAU kompensasi, bukan dua-duanya) — sistem tidak memaksa ini, jadi centang salah satu
      sesuai kondisi pegawai yang bersangkutan. "Uang Makan/Uang Saku — Hari Transit H-1/H+1" dan
      "Uang Makan — Konsumsi Ditanggung Sebagian Panitia" adalah baris TAMBAHAN yang DIJUMLAHKAN
      dengan "Uang Makan"/"Uang Saku" biasa (bukan menimpanya) — atur Hari pada tiap baris di tabel
      pratinjau di bawah supaya totalnya benar (mis. 2 hari normal di "Uang Makan" @100% + 1 hari
      transit di "Uang Makan — Hari Transit H-1/H+1" @25%, BUKAN 3 hari penuh di salah satu baris).
    </p>

    <div class="subjudul">Penandatangan</div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Pejabat Berwenang (Surat Jalan) — Judul/Jabatan</label>
        <input type="text" name="authorizing_official_title" maxlength="150" value="{{ $isi('authorizing_official_title') }}">
      </div>
      <div class="bidang">
        <label>Pejabat Berwenang — Nama</label>
        <select name="authorizing_official_name">
          <option value="">— Pilih —</option>
          @foreach ($signatoryEmployees as $s)
            <option value="{{ $s->full_name }}" @selected($isi('authorizing_official_name') === $s->full_name)>{{ $s->full_name }} ({{ $s->nrp }})</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Penandatangan Rincian Lumpsum 1 — Judul/Jabatan</label>
        <input type="text" name="lumpsum_signatory_1_title" maxlength="150" value="{{ $isi('lumpsum_signatory_1_title') }}">
      </div>
      <div class="bidang">
        <label>Penandatangan Rincian Lumpsum 1 — Nama</label>
        <select name="lumpsum_signatory_1_name">
          <option value="">— Pilih —</option>
          @foreach ($signatoryEmployees as $s)
            <option value="{{ $s->full_name }}" @selected($isi('lumpsum_signatory_1_name') === $s->full_name)>{{ $s->full_name }} ({{ $s->nrp }})</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Penandatangan Rincian Lumpsum 2 — Judul/Jabatan</label>
        <input type="text" name="lumpsum_signatory_2_title" maxlength="150" value="{{ $isi('lumpsum_signatory_2_title') }}">
      </div>
      <div class="bidang">
        <label>Penandatangan Rincian Lumpsum 2 — Nama</label>
        <select name="lumpsum_signatory_2_name">
          <option value="">— Pilih —</option>
          @foreach ($signatoryEmployees as $s)
            <option value="{{ $s->full_name }}" @selected($isi('lumpsum_signatory_2_name') === $s->full_name)>{{ $s->full_name }} ({{ $s->nrp }})</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="bidang">
      <label>Pegawai yang Berangkat</label>
      <div class="picker">
        <div class="picker-alat">
          <input type="text" id="cari-pegawai" placeholder="Cari nama/NRP...">
          <label><input type="checkbox" id="pilih-semua"> Pilih Semua</label>
        </div>
        <div class="picker-daftar" id="daftar-pegawai">
          @forelse ($employees as $e)
            <div class="picker-baris" data-cari="{{ strtolower($e->full_name.' '.$e->nrp) }}">
              <input type="checkbox" name="employee_ids[]" value="{{ $e->id }}" class="cek-pegawai" @checked(in_array($e->id, $dipilih, true))>
              <div>
                {{ $e->full_name }}
                <small>{{ $e->nrp }} — {{ $e->position_name }}{{ $bankWide && isset($e->office_name) ? ' — '.$e->office_name : '' }}</small>
              </div>
            </div>
          @empty
            <div class="picker-baris">Tidak ada pegawai.</div>
          @endforelse
        </div>
        <div class="picker-hitung"><span id="jumlah-terpilih">0</span> pegawai dicentang</div>
      </div>
    </div>

    @if ($preview)
      @php
        $grandTotal = 0;
        $componentLabels = [
          'uang_makan' => 'Uang Makan', 'uang_saku' => 'Uang Saku', 'hotel' => 'Plafon Hotel',
          'hotel_kompensasi' => 'Kompensasi Tidak Ambil Hotel',
          'angkutan_setempat' => 'Plafon Angkutan', 'transportasi_tujuan' => 'Plafon Transport',
          'uang_makan_h1' => 'Uang Makan H-1/H+1', 'uang_saku_h1' => 'Uang Saku H-1/H+1',
          'uang_makan_konsumsi' => 'Uang Makan Konsumsi Sebagian',
        ];
        $centsKeyFor = [
          'uang_makan' => 'uang_makan_cents', 'uang_saku' => 'uang_saku_cents', 'hotel' => 'estimasi_hotel_cents',
          'hotel_kompensasi' => 'hotel_kompensasi_cents',
          'angkutan_setempat' => 'estimasi_angkutan_setempat_cents', 'transportasi_tujuan' => 'estimasi_transportasi_tujuan_cents',
          'uang_makan_h1' => 'uang_makan_h1_cents', 'uang_saku_h1' => 'uang_saku_h1_cents',
          'uang_makan_konsumsi' => 'uang_makan_konsumsi_cents',
        ];
      @endphp
      <div class="pratinjau">
        <div class="judul">Pratinjau &amp; Rincian Persen/Hari per Pegawai</div>
        <div class="pratinjau-gulir">
        <table>
          <thead>
            <tr>
              <th>Pegawai</th>
              @foreach ($includedComponents as $key)
                <th>{{ $componentLabels[$key] }}<br><span style="font-weight:400;opacity:.7">% &times; hari</span></th>
              @endforeach
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($preview as $row)
              @php
                $c = $row['cents'];
                $rowTotal = $c['uang_makan_cents'] + $c['uang_saku_cents'] + ($c['estimasi_hotel_cents'] ?? 0)
                  + ($c['hotel_kompensasi_cents'] ?? 0) + ($c['estimasi_angkutan_setempat_cents'] ?? 0) + ($c['estimasi_transportasi_tujuan_cents'] ?? 0)
                  + ($c['uang_makan_h1_cents'] ?? 0) + ($c['uang_saku_h1_cents'] ?? 0) + ($c['uang_makan_konsumsi_cents'] ?? 0);
                $grandTotal += $rowTotal;
                $mataUang = $row['currency'] === 'USD' ? '$' : 'Rp';
                $desimal = $row['currency'] === 'USD' ? 2 : 0;
                $fmt = fn (?int $cents) => $cents === null ? '—' : $mataUang.number_format($cents / 100, $desimal, ',', '.');
              @endphp
              <tr>
                <td>{{ $row['full_name'] }}<br><small style="opacity:.7">{{ $row['nrp'] }}</small></td>
                @foreach ($includedComponents as $key)
                  @php $centsKey = $centsKeyFor[$key]; $opt = $row['options'][$key]; @endphp
                  <td>
                    <div class="opsi-komponen">
                      <input type="number" class="sel-persen" name="employee_options[{{ $row['employee_id'] }}][{{ $key }}][percent]" value="{{ $opt['percent'] }}" min="1" max="100">%
                      &times;
                      <input type="number" class="sel-hari" name="employee_options[{{ $row['employee_id'] }}][{{ $key }}][days]" value="{{ $opt['days'] }}" min="1" max="366">hr
                    </div>
                    <div class="subtotal">{{ $c[$centsKey] === 0 ? '—' : $fmt($c[$centsKey]) }}</div>
                  </td>
                @endforeach
                <td>{{ $fmt($rowTotal) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr><td colspan="{{ count($includedComponents) + 1 }}">TOTAL SELURUH PEGAWAI</td><td>Rp{{ number_format($grandTotal / 100, 0, ',', '.') }}</td></tr>
          </tfoot>
        </table>
        </div>
        <div class="ket">
          Persen &times; Hari menentukan berapa besar tarif per-hari/unit yang benar-benar
          dibayarkan ke pegawai ini (mis. 25% &times; 1 hari untuk hari transit H-1/H+1 per BPP
          §III.B.3) — bawaan 100% &times; jumlah hari perjalanan (Transportasi Tujuan bawaan 1 hari,
          sesuai sifatnya sekali jalan, bukan per hari). Ubah nilainya per pegawai lalu klik
          "Hitung Perkiraan" lagi untuk melihat hasilnya sebelum disimpan — nilai yang tampil di
          sini adalah yang akan benar-benar tersimpan.
        </div>
      </div>
    @elseif ($dipilih !== [])
      <div class="pesan" style="background:var(--emas-muda);border:1px solid #E8D9A0;color:#6B540A;margin-bottom:16px">
        Klik "Hitung Perkiraan" untuk melihat dan menyesuaikan persen/hari tiap komponen per pegawai sebelum menyimpan.
      </div>
    @endif

    <div class="aksi" style="display:flex;gap:8px;margin-top:14px">
      <button type="submit" formmethod="GET" formaction="{{ route('sppd-memo.create') }}" formnovalidate class="btn luar">Hitung Perkiraan</button>
      <button type="submit" class="btn">Simpan & Setujui</button>
      <a href="{{ route('sppd-memo.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection

@section('skrip')
<script>
function perbaruiBlokKondisional() {
  var kategori = document.getElementById('trip_category').value;
  document.querySelectorAll('.blok-kondisional').forEach(function (blok) {
    var daftar = blok.getAttribute('data-untuk').split(',');
    blok.classList.toggle('tampil', daftar.indexOf(kategori) !== -1);
  });
}
document.addEventListener('DOMContentLoaded', perbaruiBlokKondisional);

(function () {
  var cariInput = document.getElementById('cari-pegawai');
  var pilihSemua = document.getElementById('pilih-semua');
  var baris = document.querySelectorAll('.picker-baris[data-cari]');
  var jumlahEl = document.getElementById('jumlah-terpilih');
  var form = document.getElementById('form-sppd-memo');

  function perbaruiJumlah() {
    var n = document.querySelectorAll('.cek-pegawai:checked').length;
    jumlahEl.textContent = n;
  }

  cariInput.addEventListener('input', function () {
    var kata = cariInput.value.toLowerCase().trim();
    baris.forEach(function (b) {
      var cocok = b.getAttribute('data-cari').indexOf(kata) !== -1;
      b.style.display = cocok ? 'flex' : 'none';
    });
  });

  pilihSemua.addEventListener('change', function () {
    baris.forEach(function (b) {
      if (b.style.display === 'none') { return; }
      var cek = b.querySelector('.cek-pegawai');
      if (cek) { cek.checked = pilihSemua.checked; }
    });
    perbaruiJumlah();
  });

  document.getElementById('daftar-pegawai').addEventListener('change', function (e) {
    if (e.target.classList.contains('cek-pegawai')) { perbaruiJumlah(); }
  });

  form.addEventListener('submit', function (e) {
    if (document.querySelectorAll('.cek-pegawai:checked').length === 0) {
      e.preventDefault();
      alert('Pilih minimal satu pegawai.');
    }
  });

  perbaruiJumlah();
})();
</script>
@endsection
