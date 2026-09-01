<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Lampiran Penerima {{ $batch->reference_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:10px;color:#1a1a1a;margin:20px}
  .kop{text-align:right;margin-bottom:8px}
  .kop img{height:34px}
  .judul{text-align:center;font-weight:700;font-size:12px;margin:0 0 16px;text-transform:uppercase}
  table.rincian{width:100%;border-collapse:collapse;table-layout:fixed}
  table.rincian th,table.rincian td{border:1px solid #1a1a1a;padding:4px 5px;font-size:9px;word-wrap:break-word}
  table.rincian th{background:#1a6b3c;color:#fff;text-align:center;font-weight:700}
  table.rincian td.angka{text-align:right}
  table.rincian tfoot td{font-weight:700;background:#f0f0f0;text-align:center}
  table.rincian tfoot td.angka{text-align:right}
  .tempat-tanggal{text-align:center;margin-top:18px;font-size:10px}
  .kaki{margin-top:16px;font-size:8px;color:#777;border-top:1px solid #ccc;padding-top:4px}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyLogo::dataUri() }}" alt="Bank NTB Syariah">
  </div>

  <div class="judul">
    Daftar Pelimpahan Bekal Cuti Tahunan Tahun {{ $items->first()->year ?? \Carbon\Carbon::parse($batch->created_at)->year }}
    Pegawai PT. Bank NTB — {{ $batch->payer_scope === 'hc' ? 'Kantor Pusat' : ($batch->office_name ?? 'Kantor Cabang') }}
  </div>

  <table class="rincian">
    <thead>
      <tr>
        <th style="width:3%">NO.</th>
        <th style="width:8%">NRP</th>
        <th style="width:17%">Nama</th>
        <th style="width:13%">Unit Kerja</th>
        <th style="width:7%">Jenis Cuti</th>
        <th style="width:8.5%">Bekal Cuti</th>
        <th style="width:8.5%">Tunjangan PPh</th>
        <th style="width:8.5%">Jumlah</th>
        <th style="width:8.5%">Dipotong PPh</th>
        <th style="width:9%">Jumlah Diterima</th>
        <th style="width:9%">No. Simpeda</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($items as $i => $it)
        <tr>
          <td style="text-align:center">{{ $i + 1 }}</td>
          <td>{{ $it->nrp }}</td>
          <td>{{ strtoupper($it->full_name) }}</td>
          <td>{{ strtoupper($it->division ?? $batch->office_name ?? '—') }}</td>
          <td>Cuti Tahunan</td>
          <td class="angka">{{ number_format($it->gross_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">{{ number_format($it->tax_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">{{ number_format(($it->gross_cents + $it->tax_cents) / 100, 0, ',', '.') }}</td>
          <td class="angka">{{ number_format($it->tax_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">{{ number_format($it->net_cents / 100, 0, ',', '.') }}</td>
          <td>{{ $it->bank_account_number ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5">JUMLAH</td>
        <td class="angka">{{ number_format($batch->total_gross_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">{{ number_format($batch->total_tax_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">{{ number_format(($batch->total_gross_cents + $batch->total_tax_cents) / 100, 0, ',', '.') }}</td>
        <td class="angka">{{ number_format($batch->total_tax_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">{{ number_format($batch->total_net_cents / 100, 0, ',', '.') }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <p class="tempat-tanggal">Mataram &nbsp;{{ \Carbon\Carbon::parse($batch->created_at)->translatedFormat('d F Y') }}</p>

  <p class="kaki">
    Dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
    berdasarkan batch pembayaran {{ $batch->reference_number }}.
  </p>
</body>
</html>
