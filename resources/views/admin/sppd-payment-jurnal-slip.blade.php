<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Jurnal Slip {{ $batch->batch_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11px;color:#1a1a1a;margin:30px}
  .kop{margin-bottom:16px}
  .kop img{height:34px}
  .judul{text-align:center;font-weight:700;font-size:13px;margin:6px 0 2px}
  .sub{text-align:center;font-size:11px;margin-bottom:16px}
  .blok-label{font-weight:700;text-decoration:underline;margin:14px 0 4px}
  table.jurnal-baris{width:100%;border-collapse:collapse;margin-bottom:6px}
  table.jurnal-baris td{padding:3px 0;font-size:11px;vertical-align:top}
  table.jurnal-baris td.kode{width:130px}
  table.jurnal-baris td.angka{text-align:right;width:120px}
  .keterangan{font-size:10.5px;color:#333;margin:2px 0 14px;line-height:1.6}
  table.rincian-pajak{width:100%;border-collapse:collapse;margin:6px 0 18px}
  table.rincian-pajak th,table.rincian-pajak td{border:1px solid #999;padding:5px 7px;font-size:10.5px}
  table.rincian-pajak th{background:#f0f0f0;text-align:left}
  table.rincian-pajak td.angka{text-align:right}
  table.rincian-pajak tfoot td{font-weight:700;background:#f7f7f7}
  .ttd{width:100%;border-collapse:collapse;margin-top:30px}
  .ttd td{border:1px solid #1a1a1a;width:33.33%;text-align:center;font-weight:700;background:#eaf3fb;padding:6px;height:60px;vertical-align:top}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyLogo::dataUri() }}" alt="Bank NTB Syariah">
  </div>

  <div class="judul">JURNAL SLIP</div>
  <div class="sub">
    {{ $batch->payer_scope === 'hc' ? 'KANTOR PUSAT' : ($batch->office_name ?? 'KANTOR CABANG') }}<br>
    TGL. {{ \Carbon\Carbon::parse($batch->created_at)->translatedFormat('d F Y') }}
  </div>

  <div class="blok-label">DEBET :</div>
  <table class="jurnal-baris">
    <tr>
      <td class="kode">{{ $batch->tax_expense_account_code }}</td>
      <td>{{ $batch->tax_expense_account_name }}</td>
      <td class="angka">Rp {{ number_format(($batch->total_tax_cents ?? 0) / 100, 0, ',', '.') }}</td>
    </tr>
  </table>
  <div class="keterangan">
    Keterangan :<br>
    Pelimpahan Ke Rek. Penampungan atas pajak PPh 21 SPPD an {{ $items->first()->full_name ?? '—' }} dkk :<br>
    untuk {{ $batch->purpose }}
  </div>

  <table class="rincian-pajak">
    <thead><tr><th>Pegawai</th><th style="text-align:right">PPh 21</th></tr></thead>
    <tbody>
      @foreach ($items as $i)
        <tr>
          <td>{{ $i->full_name }}</td>
          <td class="angka">Rp {{ number_format(($i->tax_cents ?? 0) / 100, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td>Jumlah</td><td class="angka">Rp {{ number_format(($batch->total_tax_cents ?? 0) / 100, 0, ',', '.') }}</td></tr>
    </tfoot>
  </table>

  <div class="blok-label">KREDIT :</div>
  <table class="jurnal-baris">
    <tr>
      <td class="kode">{{ $batch->tax_account_code }}</td>
      <td>{{ $batch->tax_account_name }}</td>
      <td class="angka">Rp {{ number_format(($batch->total_tax_cents ?? 0) / 100, 0, ',', '.') }}</td>
    </tr>
  </table>

  <table class="ttd">
    <tr>
      <td>Bagian yang bersangkutan</td>
      <td>Pembukuan</td>
      <td>Mengetahui</td>
    </tr>
  </table>
</body>
</html>
