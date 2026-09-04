<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Lampiran Penerima {{ $batch->spkl_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11px;color:#1a1a1a;margin:30px}
  .kop img{height:34px}
  .judul{font-weight:700;font-size:12px;margin:14px 0 12px}
  table.rincian{width:100%;border-collapse:collapse}
  table.rincian th,table.rincian td{border:1px solid #1a1a1a;padding:6px 8px;font-size:11px}
  table.rincian th{background:#f0f0f0;text-align:left}
  table.rincian td.angka,table.rincian th.angka{text-align:right}
  table.rincian tfoot td{font-weight:700;background:#f7f7f7}
  .kaki{margin-top:16px;font-size:8px;color:#777;border-top:1px solid #ccc;padding-top:4px}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyProfile::logoDataUri() }}" alt="Bank NTB Syariah">
  </div>

  <div class="judul">LAMPIRAN PENERIMA UANG LEMBUR</div>

  <table class="rincian">
    <thead>
      <tr><th style="width:36px">NO</th><th>NAMA</th><th class="angka" style="width:130px">JUMLAH</th><th style="width:150px">NOMOR REKENING</th></tr>
    </thead>
    <tbody>
      @foreach ($items as $i => $it)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $it->full_name }}</td>
          <td class="angka">{{ number_format($it->net_cents / 100, 0, ',', '.') }}</td>
          <td>{{ $it->bank_account_number ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="2">Jumlah</td><td class="angka">{{ number_format($batch->total_net_cents / 100, 0, ',', '.') }}</td><td></td></tr>
    </tfoot>
  </table>

  <p class="kaki">
    Dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
    berdasarkan batch pembayaran {{ $batch->spkl_number }}.
  </p>
</body>
</html>
