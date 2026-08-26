<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Slip Gaji {{ date('F Y', strtotime($s->period)) }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11.5px;color:#1a1a1a;margin:32px}
  .kop{text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:18px}
  .kop h1{font-size:15px;margin:0 0 2px}
  .kop p{margin:0;font-size:11px;color:#444}
  .judul{text-align:center;margin-bottom:16px}
  .judul h2{font-size:13px;text-decoration:underline;margin:0}

  table.header{width:100%;border-collapse:collapse;margin-bottom:16px}
  table.header td{vertical-align:top;padding:0}
  table.header table.data td{padding:2px 4px;font-size:11px}
  table.header table.data td.label{width:90px;color:#333}
  table.header table.data td.sep{width:12px}

  table.rincian{width:100%;border-collapse:collapse;margin:10px 0}
  table.rincian td.kolom{vertical-align:top;width:50%;padding:0 6px}
  table.rincian table.isi{width:100%;border-collapse:collapse}
  table.rincian table.isi th{background:#e9e9e9;text-align:left;border:1px solid #999;
    padding:5px 7px;font-size:10.5px;text-transform:uppercase}
  table.rincian table.isi td{border:1px solid #999;padding:4px 7px;font-size:10.5px}
  table.rincian table.isi td.angka{text-align:right;white-space:nowrap}
  table.rincian table.isi tr.subjudul td{background:#f5f5f5;font-weight:700;font-size:10px}
  table.rincian table.isi tr.jumlah td{font-weight:700;background:#f0f0f0}

  table.thp{width:100%;border-collapse:collapse;margin:8px 0 14px}
  table.thp td{border:1px solid #1a1a1a;padding:7px 9px;font-size:12px;font-weight:700}
  table.thp td.angka{text-align:right}

  .pending{margin-top:12px;padding:11px 13px;background:#FBF4DE;border:1px solid #E8D9A0;
    border-radius:6px;font-size:10.5px;color:#6B540A;line-height:1.7}
  .pending ul{margin:6px 0 0 18px}

  .tanggal{text-align:right;margin-top:26px;font-size:11px}
  .ttd{width:100%;margin-top:8px}
  .ttd table{width:100%}
  .ttd td{width:50%;text-align:center;font-size:11.5px;vertical-align:top}
  .ttd .garis{margin-top:56px;border-top:1px solid #1a1a1a;padding-top:4px;display:inline-block;min-width:200px}
  .catatan{margin-top:24px;font-size:10px;color:#666}
</style>
</head>
<body>
  <div class="kop">
    <h1>Bank NTB Syariah</h1>
    <p>Kantor Pusat — Jl. Pejanggik No. 30 Mataram</p>
  </div>

  <div class="judul">
    <h2>SLIP GAJI — {{ strtoupper(date('F Y', strtotime($s->period))) }}</h2>
  </div>

  <table class="header">
    <tr>
      <td style="width:50%">
        <table class="data">
          <tr><td class="label">NRP</td><td class="sep">:</td><td>{{ $s->nrp }}</td></tr>
          <tr><td class="label">Nama Pegawai</td><td class="sep">:</td><td>{{ $s->full_name }}</td></tr>
          <tr><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $s->jabatan_name }}</td></tr>
          <tr><td class="label">Unit Kerja</td><td class="sep">:</td><td>{{ $s->unit_kerja_name }}</td></tr>
        </table>
      </td>
      <td style="width:50%">
        <table class="data">
          <tr><td class="label">Kantor</td><td class="sep">:</td><td>{{ $s->unit_kerja_name }}</td></tr>
          <tr><td class="label">Bulan</td><td class="sep">:</td><td>{{ strtoupper(date('F Y', strtotime($s->period))) }}</td></tr>
          <tr><td class="label">No. Simpeda</td><td class="sep">:</td><td>{{ $s->nomor_simpeda ?? '-' }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  @php
    $rp = fn ($cents) => 'Rp'.number_format(($cents ?? 0) / 100, 0, ',', '.');

    // Baris tunggal per kategori (dijumlah kalau lebih dari satu — KITIR
    // hanya sediakan satu baris untuk kategori ini, tapi data tetap
    // mendukung >1 baris untuk jaga-jaga).
    $single = fn (string $key) => $deductionsByType->get($key, collect())->sum('amount_cents');
    $singleAdd = fn (string $key) => $additionsByType->get($key, collect())->sum('amount_cents');

    // Kategori yang KITIR beri nomor (Pinjaman/Arisan/Koperasi/Bank/BPR/
    // Lainnya) — 0 baris TETAP tampil satu baris "Rp0" (label {nomor 1})
    // supaya layout tidak "meloncat" tiap bulan tergantung data mana yang
    // kebetulan kosong (pola sama keputusan Fase I).
    $numbered = function (string $key, string $label) use ($deductionsByType) {
        $rows = $deductionsByType->get($key, collect())->values();

        if ($rows->isEmpty()) {
            return [[$label.' 1', 0]];
        }

        return $rows->map(fn ($r, $i) => [$label.' '.($i + 1), (int) $r->amount_cents])->all();
    };

    $tunjPengobatan = $singleAdd('tunjangan_pengobatan');
    $pajak = $s->pph21_cents ?? 0;
    $astek = $single('astek');

    $barisPinjaman = $numbered('kasbon_pinjaman', 'Kasbon/Pinjaman');
    $barisArisan = $numbered('arisan', 'Arisan');
    $barisKoperasi = $numbered('koperasi', 'Koperasi');
    $barisBank = $numbered('bank', 'Bank');
    $barisBpr = $numbered('bpr', 'BPR');
    $barisLainnya = $numbered('lainnya', 'Lainnya');

    $jumlahPenghasilan = $s->imbalan_kerja_cents + $s->tunjangan_jabatan_cents
        + $tunjPengobatan + $s->tunjangan_penyesuaian_cents;

    // Jumlah SEMUA baris pay_payslip_deductions apa pun jenisnya — cara
    // paling sederhana & tidak mungkin lupa satu kategori (termasuk
    // astek, kasbon_pinjaman, dan seluruh kategori Potongan 2 sekaligus).
    $semuaPotonganAdHoc = $deductionsByType->flatten(1)->sum('amount_cents');

    $jumlahPotongan = $s->iuran_pensiun_pegawai_cents + $s->iuran_tht_pegawai_cents
        + $semuaPotonganAdHoc + $pajak;

    $thp = $jumlahPenghasilan - $jumlahPotongan;
  @endphp

  <table class="rincian">
    <tr>
      <td class="kolom">
        <table class="isi">
          <thead><tr><th colspan="2">Penghasilan</th></tr></thead>
          <tbody>
            <tr><td>Imbalan Kerja</td><td class="angka">{{ $rp($s->imbalan_kerja_cents) }}</td></tr>
            <tr><td>Tunjangan Jabatan</td><td class="angka">{{ $rp($s->tunjangan_jabatan_cents) }}</td></tr>
            <tr><td>Tunj. Pengobatan</td><td class="angka">{{ $rp($tunjPengobatan) }}</td></tr>
            <tr><td>Tunjangan Penyesuaian</td><td class="angka">{{ $rp($s->tunjangan_penyesuaian_cents) }}</td></tr>
            <tr class="jumlah"><td>Jumlah</td><td class="angka">{{ $rp($jumlahPenghasilan) }}</td></tr>
          </tbody>
        </table>
      </td>
      <td class="kolom">
        <table class="isi">
          <thead><tr><th colspan="2">Potongan 1</th></tr></thead>
          <tbody>
            <tr><td>Iuran Pensiun (7%)</td><td class="angka">{{ $rp($s->iuran_pensiun_pegawai_cents) }}</td></tr>
            <tr><td>Iuran THT (5%)</td><td class="angka">{{ $rp($s->iuran_tht_pegawai_cents) }}</td></tr>
            <tr><td>Astek/BPJS Ketenagakerjaan</td><td class="angka">{{ $rp($astek) }}</td></tr>
            @foreach ($barisPinjaman as [$label, $amount])
              <tr><td>{{ $label }}</td><td class="angka">{{ $rp($amount) }}</td></tr>
            @endforeach
            @if ($s->pph21_cents !== null)
              <tr><td>Pajak (PPh 21, Golongan {{ $s->pph21_golongan }})</td><td class="angka">{{ $rp($pajak) }}</td></tr>
            @else
              <tr><td>Pajak (PPh 21)</td><td class="angka">{{ $rp(0) }}</td></tr>
            @endif
          </tbody>
        </table>
        <table class="isi" style="margin-top:6px">
          <thead><tr><th colspan="2">Potongan 2</th></tr></thead>
          <tbody>
            <tr><td>Asuransi</td><td class="angka">{{ $rp($single('asuransi')) }}</td></tr>
            <tr><td>Bazis</td><td class="angka">{{ $rp($single('bazis')) }}</td></tr>
            <tr><td>Iuran</td><td class="angka">{{ $rp($single('iuran')) }}</td></tr>
            @foreach ($barisArisan as [$label, $amount])
              <tr><td>{{ $label }}</td><td class="angka">{{ $rp($amount) }}</td></tr>
            @endforeach
            @foreach ($barisKoperasi as [$label, $amount])
              <tr><td>{{ $label }}</td><td class="angka">{{ $rp($amount) }}</td></tr>
            @endforeach
            <tr><td>Kesra</td><td class="angka">{{ $rp($single('kesra')) }}</td></tr>
            @foreach ($barisBank as [$label, $amount])
              <tr><td>{{ $label }}</td><td class="angka">{{ $rp($amount) }}</td></tr>
            @endforeach
            <tr><td>LKP</td><td class="angka">{{ $rp($single('lkp')) }}</td></tr>
            @foreach ($barisBpr as [$label, $amount])
              <tr><td>{{ $label }}</td><td class="angka">{{ $rp($amount) }}</td></tr>
            @endforeach
            @foreach ($barisLainnya as [$label, $amount])
              <tr><td>{{ $label }}</td><td class="angka">{{ $rp($amount) }}</td></tr>
            @endforeach
            <tr class="jumlah"><td>Jumlah Potongan</td><td class="angka">{{ $rp($jumlahPotongan) }}</td></tr>
          </tbody>
        </table>
      </td>
    </tr>
  </table>

  <table class="thp">
    <tr>
      <td>THP (Take Home Pay)</td>
      <td class="angka">{{ $rp($thp) }}</td>
    </tr>
  </table>

  <div class="pending">
    Catatan penting mengenai angka di atas:
    <ul>
      @foreach (json_decode($s->pending_components, true) as $p)
        <li>{{ $p }}</li>
      @endforeach
    </ul>
  </div>

  <p class="tanggal">Mataram, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>

  <div class="ttd">
    <table>
      <tr>
        <td>
          Menyetujui,<br><br>
          <span class="garis">{{ $s->full_name }}</span>
        </td>
        <td>
          PT. Bank NTB Syariah<br>
          Divisi SDI<br><br>
          <span class="garis">{{ $s->approver_name }}<br>{{ $s->approver_position }}</span>
        </td>
      </tr>
    </table>
  </div>

  <p class="catatan">
    Dokumen ini dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
    dan sah tanpa tanda tangan basah selama status payroll run tercatat "disetujui" pada sistem.
  </p>
</body>
</html>
