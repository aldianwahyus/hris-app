<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Nota Debet {{ $batch->batch_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11px;color:#1a1a1a;margin:30px}
  .kop{text-align:center;margin-bottom:6px}
  .kop img{height:36px}
  .judul{text-align:center;font-weight:700;font-size:13px;margin:10px 0 2px}
  .sub{text-align:center;font-size:11px;text-decoration:underline;margin-bottom:14px}
  table.kepada{width:100%;border-collapse:collapse;margin-bottom:10px}
  table.kepada td{padding:2px 0;font-size:11px;vertical-align:top}
  table.kepada td.lbl{width:110px}
  .deskripsi{margin:10px 0;font-size:11px;line-height:1.6}
  table.jurnal{width:100%;border-collapse:collapse;margin:14px 0}
  table.jurnal th,table.jurnal td{border:1px solid #1a1a1a;padding:6px 9px;font-size:11px}
  table.jurnal th{background:#f0f0f0;text-align:left}
  table.jurnal td.angka{text-align:right}
  .rincian-cat{margin:10px 0;font-size:11px}
  table.lampiran{width:100%;border-collapse:collapse;margin:8px 0 18px}
  table.lampiran th,table.lampiran td{border:1px solid #999;padding:5px 7px;font-size:10.5px}
  table.lampiran th{background:#f0f0f0;text-align:left}
  table.lampiran td.angka{text-align:right}
  table.lampiran tfoot td{font-weight:700;background:#f7f7f7}
  .footer{margin-top:26px;text-align:right;font-size:11px}
  .footer b{display:block;margin-top:2px}
  .coret{font-size:9px;color:#666;margin-top:20px}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyProfile::logoDataUri() }}" alt="Bank NTB Syariah">
  </div>

  <div class="judul">{{ $batch->payer_scope === 'hc' ? 'KANTOR PUSAT' : ($batch->office_name ?? 'KANTOR CABANG') }}</div>
  <div class="sub">NOTA : DEBET / KREDIT *)</div>

  <table class="kepada">
    <tr>
      <td class="lbl">KEPADA YTH</td>
      <td>: {{ $batch->payer_scope === 'hc' ? \App\Interfaces\Http\Support\CompanyProfile::name().' KANTOR PUSAT' : $batch->office_name }}</td>
      <td class="lbl" style="text-align:right;width:130px">Nomor Transaksi</td>
      <td style="width:150px">: {{ $batch->batch_number }}</td>
    </tr>
    <tr>
      <td></td><td></td>
      <td style="text-align:right">Tanggal Transaksi</td>
      <td>: {{ \Carbon\Carbon::parse($batch->created_at)->translatedFormat('d F Y') }}</td>
    </tr>
  </table>

  <div class="deskripsi">
    Telah kami bukukan untuk rekening Saudara sbb :<br>
    SPPD ke {{ $batch->destination }} untuk {{ strtoupper($batch->purpose) }} Nomor {{ $batch->group_number }}{{ $batch->source_division ? ' '.$batch->source_division : '' }}
    an {{ $items->first()->full_name ?? '—' }} dkk :
  </div>

  <table class="jurnal">
    <thead><tr><th>Jurnal</th><th style="text-align:right">Debet</th><th style="text-align:right">Kredit</th></tr></thead>
    <tbody>
      <tr>
        <td>{{ $batch->expense_account_code }} — {{ $batch->expense_account_name }}</td>
        <td class="angka">Rp {{ number_format(($batch->total_net_cents ?? 0) / 100, 0, ',', '.') }}</td>
        <td class="angka">—</td>
      </tr>
      <tr>
        <td>Terlampir (rekening pegawai)</td>
        <td class="angka">—</td>
        <td class="angka">Rp {{ number_format(($batch->total_net_cents ?? 0) / 100, 0, ',', '.') }}</td>
      </tr>
    </tbody>
  </table>

  <div class="rincian-cat">Rincian : (Rek. masing-masing Pegawai terlampir)</div>

  <table class="lampiran">
    <thead><tr><th>Pegawai</th><th>NRP</th><th>No. Rekening</th><th style="text-align:right">Jumlah Bersih</th></tr></thead>
    <tbody>
      @foreach ($items as $i)
        <tr>
          <td>{{ $i->full_name }}</td>
          <td>{{ $i->nrp }}</td>
          <td>{{ $i->bank_account_number ?? '—' }}</td>
          <td class="angka">Rp {{ number_format(($i->net_cents ?? 0) / 100, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="3">TOTAL</td><td class="angka">Rp {{ number_format(($batch->total_net_cents ?? 0) / 100, 0, ',', '.') }}</td></tr>
    </tfoot>
  </table>

  <p class="coret">*) Coret salah satu</p>

  <div class="footer">
    <span>{{ \App\Interfaces\Http\Support\CompanyProfile::name() }}</span>
    <b>{{ $batch->payer_scope === 'hc' ? 'KANTOR PUSAT' : $batch->office_name }}</b>
  </div>
</body>
</html>
