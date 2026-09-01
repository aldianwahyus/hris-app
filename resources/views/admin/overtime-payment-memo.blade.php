<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Memo Internal {{ $batch->spkl_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11px;color:#1a1a1a;margin:30px}
  .kop img{height:34px;float:right}
  .judul{text-align:center;font-weight:700;font-size:14px;text-decoration:underline;margin-bottom:16px;clear:both;padding-top:6px}
  table.data{width:100%;border-collapse:collapse;margin-bottom:10px}
  table.data td{padding:2px 0;font-size:11px;vertical-align:top}
  table.data td.lbl{width:80px}
  table.data td.sep{width:14px}
  .isi{margin:12px 0;font-size:11px;line-height:1.7;text-align:justify}
  ol.dasar{margin:8px 0 12px 20px;font-size:11px;line-height:1.7}
  table.rincian{width:100%;border-collapse:collapse;margin:10px 0}
  table.rincian th,table.rincian td{border:1px solid #1a1a1a;padding:5px 6px;font-size:10px}
  table.rincian th{background:#f0f0f0;text-align:center}
  table.rincian td.angka{text-align:right}
  table.rincian td.nama{text-align:left}
  table.rincian tfoot td{font-weight:700;background:#f7f7f7;text-align:center}
  table.rincian tfoot td.angka{text-align:right}
  .penutup{margin:14px 0;font-size:11px}
  .ttd{margin-top:34px}
  .ttd .garis{display:block;margin-top:56px;border-top:1px solid #1a1a1a;padding-top:3px;min-width:200px}
  .kaki{margin-top:16px;font-size:8px;color:#777;border-top:1px solid #ccc;padding-top:4px}
</style>
</head>
<body>
@php
  $awal = $items->min('work_date');
  $akhir = $items->max('work_date');
  $rp = fn (?int $cents) => number_format(($cents ?? 0) / 100, 0, ',', '.');
  $pertama = $items->first();
@endphp

  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyLogo::dataUri() }}" alt="Bank NTB Syariah">
  </div>

  <div class="judul">MEMO INTERNAL</div>

  <table class="data">
    <tr><td class="lbl">Kepada Yth</td><td class="sep">:</td><td>-</td></tr>
    <tr><td class="lbl">Dari</td><td class="sep">:</td><td>-</td></tr>
    <tr><td class="lbl">Tanggal</td><td class="sep">:</td><td>{{ \Carbon\Carbon::parse($batch->created_at)->translatedFormat('d F Y') }}</td></tr>
    <tr>
      <td class="lbl">Perihal</td><td class="sep">:</td>
      <td>
        Usul Pembayaran Uang Lembur tanggal
        {{ $awal ? \Carbon\Carbon::parse($awal)->format('d/m/Y') : '—' }}
        s/d {{ $akhir ? \Carbon\Carbon::parse($akhir)->format('d/m/Y') : '—' }}
      </td>
    </tr>
  </table>

  <div class="isi">Dengan hormat,</div>
  <div class="isi">
    Menunjuk Surat Perintah Kerja Lembur Nomor {{ $pertama->spkl_number ?? $batch->spkl_number }}
    tanggal {{ $pertama && $pertama->work_date ? \Carbon\Carbon::parse($pertama->work_date)->format('d/m/Y') : '—' }},
    bersama ini dapat kami sampaikan bahwa :
  </div>

  <ol class="dasar">
    <li>SK Direksi Nomor : {{ $skDireksi ?? '—' }} tentang uang makan / uang lembur</li>
    <li>Pelaksana lembur terlampir</li>
    <li>Sehubungan dengan hal tersebut diatas, kami usulkan diberikan uang lembur masing-masing kepada :</li>
  </ol>

  <table class="rincian">
    <thead>
      <tr>
        <th>No.</th><th>Nm Pegawai</th>
        <th colspan="2">Lama<br>Hari&nbsp;&nbsp;&nbsp;Jam</th>
        <th>Uang<br>Lembur</th><th>Total</th><th>PPH {{ rtrim(rtrim(number_format($batch->tax_rate_percent, 2), '0'), '.') }}%</th><th>Diterima</th><th>Keterangan</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($items as $i => $it)
        @php
          $jam = (float) ($it->planned_hours ?? 0);
          $tarifPerJam = $jam > 0 ? (int) round($it->gross_cents / $jam) : 0;
        @endphp
        <tr>
          <td>{{ $i + 1 }}</td>
          <td class="nama">{{ $it->full_name }}</td>
          <td>1</td>
          <td>{{ rtrim(rtrim(number_format($jam, 2), '0'), '.') ?: '0' }}</td>
          <td class="angka">{{ $rp($tarifPerJam) }}</td>
          <td class="angka">{{ $rp($it->gross_cents) }}</td>
          <td class="angka">{{ $rp($it->tax_cents) }}</td>
          <td class="angka">{{ $rp($it->net_cents) }}</td>
          <td></td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5">JUMLAH</td>
        <td class="angka">{{ $rp($batch->total_gross_cents) }}</td>
        <td class="angka">{{ $rp($batch->total_tax_cents) }}</td>
        <td class="angka">{{ $rp($batch->total_net_cents) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <div class="penutup">Demikian usul kami sampaikan, mohon keputusan. Terima kasih</div>

  <div class="ttd">
    <span class="garis">{{ $batch->signatory_name }}</span>
  </div>

  <p class="kaki">
    Dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
    berdasarkan batch pembayaran {{ $batch->spkl_number }}.
  </p>
</body>
</html>
