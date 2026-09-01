<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Lampiran SPPD {{ $traveler->request_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:10.5px;color:#1a1a1a;margin:26px}
  .kop{position:relative;margin-bottom:10px}
  .kop img{position:absolute;top:0;right:0;height:38px}
  .kop h1{font-size:13px;text-align:center;text-transform:uppercase;margin:0 0 4px}
  .kop .nomor{display:flex;justify-content:space-between;font-size:10.5px;margin-top:6px}
  .sub-judul{text-align:center;font-weight:700;font-size:10.5px;text-decoration:underline;
    border-top:1px solid #1a1a1a;border-bottom:1px solid #1a1a1a;padding:4px 0;margin-bottom:6px}
  table.utama{width:100%;border-collapse:collapse}
  table.utama > tbody > tr > td{border:1px solid #1a1a1a;padding:4px 6px;vertical-align:top}
  .label-baris{width:26px;text-align:center;font-weight:700}
  table.identitas td{border:0;padding:1px 0;font-size:10.5px}
  table.identitas td.lbl{width:170px}
  table.rincian{width:100%;border-collapse:collapse}
  table.rincian td{padding:1.5px 4px;font-size:10.5px;vertical-align:top}
  table.rincian td.tarif{text-align:right;width:78px}
  table.rincian td.kali{width:14px;text-align:center;color:#555}
  table.rincian td.hari{width:52px}
  table.rincian td.jml{width:90px;text-align:right;border-left:1px solid #999;padding-left:8px}
  table.rincian tr.sub td.komp{padding-left:14px}
  table.rincian tr.total td{font-weight:700;border-top:1px solid #1a1a1a;padding-top:3px}
  .kanan{text-align:right}
  .bawah{width:100%;border-collapse:collapse;margin-top:10px}
  .bawah > tbody > tr > td{border:1px solid #1a1a1a;padding:6px 8px;vertical-align:top;font-size:10.5px}
  .ttd-blok{margin-top:34px;text-align:center}
  .ttd-blok .garis{display:block;margin-top:34px;border-top:1px solid #1a1a1a;padding-top:3px}
  .f-atas{display:flex;justify-content:space-between;font-size:10.5px}
  .f-baris{margin:8px 0;font-size:10.5px}
  .f-baris .isian{display:inline-block;min-width:150px;border-bottom:1px solid #1a1a1a}
  .kaki{margin-top:16px;font-size:8px;color:#777;border-top:1px solid #ccc;padding-top:4px}
</style>
</head>
<body>
@php
  $rp = fn (int $cents) => number_format($cents / 100, 0, ',', '.');
  $totalKeseluruhan = $traveler->uang_makan_cents + $traveler->uang_saku_cents
    + ($traveler->estimasi_hotel_cents ?? 0) + ($traveler->hotel_kompensasi_cents ?? 0)
    + ($traveler->estimasi_angkutan_setempat_cents ?? 0) + ($traveler->estimasi_transportasi_tujuan_cents ?? 0)
    + ($traveler->uang_makan_h1_cents ?? 0) + ($traveler->uang_saku_h1_cents ?? 0) + ($traveler->uang_makan_konsumsi_cents ?? 0);
@endphp

<div class="kop">
  <img src="{{ \App\Interfaces\Http\Support\CompanyLogo::dataUri() }}" alt="Bank NTB Syariah">
  <h1>Lampiran Surat Perintah Perjalanan Dinas (SPPD)</h1>
  <div class="nomor">
    <span>Nomor : {{ $traveler->request_number }}</span>
    <span>Tanggal : {{ \Carbon\Carbon::parse($group->memo_date)->translatedFormat('d F Y') }}</span>
  </div>
</div>

<div class="sub-judul">PERINCIAN PERHITUNGAN BIAYA PERJALANAN DINAS</div>

<table class="utama">
  <tr>
    <td class="label-baris">A.</td>
    <td>
      <table class="identitas">
        <tr><td class="lbl">1. Nama</td><td>{{ $traveler->full_name }}</td></tr>
        <tr><td class="lbl">2. Pangkat, Gol / Ruang</td><td>{{ $traveler->person_grade ?? '—' }}</td></tr>
        <tr><td class="lbl">3. Jabatan</td><td>{{ $traveler->position_name }}</td></tr>
        <tr><td class="lbl">4. Tujuan</td><td>{{ $group->destination }}</td></tr>
      </table>
    </td>
  </tr>
  <tr>
    <td class="label-baris">B.</td>
    <td>
      Lamanya : {{ $traveler->total_days }} hari, tgl {{ \Carbon\Carbon::parse($traveler->start_date)->format('d/m/Y') }}
      s/d {{ \Carbon\Carbon::parse($traveler->end_date)->format('d/m/Y') }}
    </td>
  </tr>
  <tr>
    <td class="label-baris">C.</td>
    <td>
      <div style="font-weight:700;margin-bottom:4px">DENGAN LUMPSUM BIAYA</div>
      <table class="rincian">
        <tr><td colspan="4">1. Biaya angkutan pegawai (pp)</td><td class="jml">Rp {{ $rp(0) }}</td></tr>
        <tr><td colspan="4">2. Biaya angkutan keluarga</td><td class="jml">Rp {{ $rp(0) }}</td></tr>
        <tr><td colspan="4">3. Biaya angkutan barang</td><td class="jml">Rp {{ $rp(0) }}</td></tr>
        <tr><td colspan="5">4. Biaya uang harian :</td></tr>
        @if ($traveler->estimasi_hotel_cents !== null)
          {{-- Formulir baku TIDAK punya baris ini — hotel yang DIAMBIL
               langsung dibayar Bank ke penginapan, bukan tunai ke
               pegawai. Ditambahkan HANYA saat komponen ini dicentang,
               supaya Jumlah di bawah tetap konsisten dengan rincian
               yang tampil (bukan angka tersembunyi). --}}
          <tr class="sub">
            <td class="komp">a. Plafon Hotel (fasilitas penginapan diambil)</td>
            <td></td><td></td><td></td>
            <td class="jml">Rp {{ $rp($traveler->estimasi_hotel_cents) }}</td>
          </tr>
        @endif
        <tr class="sub">
          <td class="komp">a. Tanpa Fasilitas Penginapan</td>
          <td class="tarif">Rp {{ $rp($baris['hotel_kompensasi']['rate']) }}</td>
          <td class="kali">X</td>
          <td class="hari">{{ $baris['hotel_kompensasi']['days'] }} Hari</td>
          <td class="jml">Rp {{ $rp($baris['hotel_kompensasi']['total']) }}</td>
        </tr>
        <tr class="sub"><td class="komp">b. Uang Makan :</td></tr>
        @foreach (['100' => 100, '75' => 75, '70' => 70, '50' => 50, '30' => 30, '25' => 25] as $label => $persen)
          <tr class="sub">
            <td class="komp">- {{ $label }}% Dari Tarif</td>
            <td class="tarif">Rp {{ $rp($baris['uang_makan'][$persen]['rate']) }}</td>
            <td class="kali">X</td>
            <td class="hari">{{ $baris['uang_makan'][$persen]['days'] }} Hari</td>
            <td class="jml">Rp {{ $rp($baris['uang_makan'][$persen]['total']) }}</td>
          </tr>
        @endforeach
        <tr class="sub"><td class="komp">- Ditanggung Penyelenggara</td><td></td><td></td><td></td><td class="jml">Rp {{ $rp(0) }}</td></tr>
        <tr class="sub"><td class="komp">c. Uang Angkutan setempat :</td></tr>
        @foreach ([100, 25] as $persen)
          <tr class="sub">
            <td class="komp">- {{ $persen }}% Dari Tarif</td>
            <td class="tarif">Rp {{ $rp($baris['angkutan_setempat'][$persen]['rate']) }}</td>
            <td class="kali">X</td>
            <td class="hari">{{ $baris['angkutan_setempat'][$persen]['days'] }} Hari</td>
            <td class="jml">Rp {{ $rp($baris['angkutan_setempat'][$persen]['total']) }}</td>
          </tr>
        @endforeach
        <tr class="sub"><td class="komp">d. Uang saku :</td></tr>
        @foreach ([100, 50, 25] as $persen)
          <tr class="sub">
            <td class="komp">- {{ $persen }}% Dari Tarif</td>
            <td class="tarif">Rp {{ $rp($baris['uang_saku'][$persen]['rate']) }}</td>
            <td class="kali">X</td>
            <td class="hari">{{ $baris['uang_saku'][$persen]['days'] }} Hari</td>
            <td class="jml">Rp {{ $rp($baris['uang_saku'][$persen]['total']) }}</td>
          </tr>
        @endforeach
        <tr class="sub"><td class="komp">e. Radius :</td></tr>
        <tr class="sub"><td class="komp">&gt; 30 s/d 100 Km</td><td></td><td></td><td></td><td class="jml">Rp {{ $rp($baris['radius']['30_100']) }}</td></tr>
        <tr class="sub"><td class="komp">&gt; 100 s/d 150 Km</td><td></td><td></td><td></td><td class="jml">Rp {{ $rp($baris['radius']['100_150']) }}</td></tr>
        <tr class="sub"><td class="komp">&gt; 150 Km</td><td></td><td></td><td></td><td class="jml">Rp {{ $rp($baris['radius']['150_plus']) }}</td></tr>
        <tr class="sub">
          <td class="komp">5. Taxi Air Port - Tujuan (pp)</td>
          <td class="tarif">Rp {{ $rp($baris['transportasi_tujuan']['rate']) }}</td>
          <td class="kali">X</td>
          <td class="hari">{{ $baris['transportasi_tujuan']['days'] }} Hari</td>
          <td class="jml">Rp {{ $rp($baris['transportasi_tujuan']['total']) }}</td>
        </tr>
        <tr class="total"><td colspan="4">Jumlah</td><td class="jml">Rp {{ $rp($totalKeseluruhan) }}</td></tr>
      </table>
    </td>
  </tr>
</table>

<table class="bawah">
  <tr>
    <td style="width:50%">
      D. Telah dibayarkan kepada pegawai yang melakukan perjalanan dinas jumlah uang<br>
      Rp {{ $rp($totalKeseluruhan) }}
      <div class="ttd-blok">
        YANG MEMBAYARKAN
        <span class="garis">&nbsp;</span>
      </div>
    </td>
    <td style="width:50%">
      E. Telah diterima oleh pegawai yang melakukan perjalanan dinas jumlah uang sebesar<br>
      Rp {{ $rp($totalKeseluruhan) }}
      <div class="ttd-blok">
        YANG BEPERGIAN
        <span class="garis">( {{ $traveler->full_name }} )</span>
      </div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="f-atas">
        <strong>F. PERHITUNGAN SPPD RAMPUNG</strong>
        <span>Mataram, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</span>
      </div>
      <div class="f-baris">Ditetapkan sejumlah &nbsp; Rp <span class="isian">&nbsp;</span></div>
      <div class="f-baris">Yang telah dibayar &nbsp; Rp <span class="isian">&nbsp;</span></div>
      <div class="f-baris">Kurang / Lebih &nbsp; Rp <span class="isian">&nbsp;</span></div>
      <div class="ttd-blok" style="text-align:left">
        BENDAHARAWAN
        <span class="garis" style="display:inline-block;min-width:200px">&nbsp;</span>
      </div>
    </td>
  </tr>
</table>

<p class="kaki">
  Dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
  berdasarkan SPPD Massal {{ $group->group_number }}. Plafon Hotel/Angkutan/Transportasi bersifat
  at-cost (maksimal) — realisasi sesuai tagihan sesungguhnya.
</p>
</body>
</html>
