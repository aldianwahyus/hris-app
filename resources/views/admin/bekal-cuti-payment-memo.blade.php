<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Memo Internal {{ $batch->reference_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11px;color:#1a1a1a;margin:32px}
  .kop{position:relative;margin-bottom:14px}
  .kop img{height:38px}
  .judul{text-align:center;font-weight:700;font-size:14px;text-decoration:underline;margin:0 0 14px}
  table.data{width:100%;border-collapse:collapse;margin-bottom:6px}
  table.data td{padding:1px 0;vertical-align:top;font-size:11px}
  table.data td.label{width:90px}
  table.data td.sep{width:14px}
  hr{border:none;border-top:1px solid #1a1a1a;margin:10px 0 14px}
  .isi{font-size:11px;line-height:1.7;text-align:justify}
  .daftar{margin:12px 0;font-size:11px;line-height:1.9}
  .ttd{margin-top:36px;font-size:11px}
  .ttd .garis{display:block;margin-top:52px;font-weight:700;text-decoration:underline}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyProfile::logoDataUri() }}" alt="Bank NTB Syariah">
  </div>

  <div class="judul">MEMO INTERNAL</div>

  <table class="data">
    <tr><td class="label">Kepada Yth</td><td class="sep">:</td><td>&nbsp;</td></tr>
    <tr><td class="label">Dari</td><td class="sep">:</td><td>&nbsp;</td></tr>
    <tr><td class="label">Tanggal</td><td class="sep">:</td><td>{{ \Carbon\Carbon::parse($batch->created_at)->format('d/m/Y') }}</td></tr>
    <tr><td class="label">Perihal</td><td class="sep">:</td><td>Usul Biaya Uang Bekal CUTI TAHUNAN Tahun {{ $items->first()->year ?? \Carbon\Carbon::parse($batch->created_at)->year }}</td></tr>
  </table>
  <hr>

  <div class="isi">
    Dengan hormat,
    <br><br>
    Berdasarkan SK Direksi PT. Bank NTB Nomor. SK/01.12/64/071/2018 tanggal 24 Juli 2018, tentang CUTI
    PEGAWAI PT. BANK NTB bahwa dalam pelaksanaan Cuti Besar dapat diberikan Bekal Cuti 2 (dua) kali gaji
    terakhir sebelum pajak sedangkan Cuti Tahunan diberikan Bekal Cuti 1 (satu) kali gaji terakhir sebelum
    pajak.
    <br><br>
    Sehubungan dengan hal tersebut diusulkan pembayaran Bekal CUTI TAHUNAN Tahun
    {{ $items->first()->year ?? \Carbon\Carbon::parse($batch->created_at)->year }} Pegawai
    {{ $batch->payer_scope === 'hc' ? 'KANTOR PUSAT' : strtoupper($batch->office_name ?? '') }} an.
  </div>

  <div class="daftar">
    @foreach ($items as $i)
      - {{ strtoupper($i->full_name) }} ( {{ strtoupper($i->position_name) }} )&nbsp;&nbsp;:
      Rp. {{ number_format($i->gross_cents / 100, 0, ',', '.') }}&nbsp;&nbsp;&nbsp;&nbsp;
      PPh : Rp.{{ number_format($i->tax_cents / 100, 0, ',', '.') }}<br>
    @endforeach
  </div>

  <div class="isi">
    Sebagai bahan pertimbangan terlampir disampaikan surat ijin cuti yang bersangkutan untuk selanjutnya
    mohon pertimbangan keputusan.
    <br><br>
    Terima kasih.
  </div>

  <div class="ttd">
    Mataram, &nbsp;{{ \Carbon\Carbon::parse($batch->created_at)->translatedFormat('d F Y') }}
    <span class="garis">{{ $batch->signatory_name }}</span>
  </div>
</body>
</html>
