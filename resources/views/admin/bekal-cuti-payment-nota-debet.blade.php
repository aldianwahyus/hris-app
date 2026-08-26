<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Nota Debet {{ $batch->reference_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:12px;color:#1a1a1a;margin:32px}
  .kop{position:relative;text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:18px}
  .kop img{position:absolute;top:0;right:0;height:40px}
  .kop h1{font-size:15px;margin:0 0 2px}
  .kop p{margin:0;font-size:11px;color:#444}
  .judul{text-align:center;margin-bottom:4px}
  .judul h2{font-size:13px;text-decoration:underline;margin:0}
  .judul p{margin:2px 0 0;font-size:11px}
  table.data{width:100%;border-collapse:collapse;margin:18px 0}
  table.data td{padding:4px 6px;vertical-align:top;font-size:11.5px}
  table.data td.label{width:180px;color:#333}
  table.data td.sep{width:14px}
  table.jurnal{width:100%;border-collapse:collapse;margin:18px 0}
  table.jurnal th,table.jurnal td{border:1px solid #999;padding:7px 9px;font-size:11.5px}
  table.jurnal th{background:#f0f0f0;text-align:left}
  table.jurnal td.angka{text-align:right}
  table.jurnal tfoot td{font-weight:700;background:#f7f7f7}
  .lampiran-judul{margin-top:22px;font-size:12px;font-weight:700;text-decoration:underline}
  table.rincian{width:100%;border-collapse:collapse;margin:10px 0}
  table.rincian th,table.rincian td{border:1px solid #999;padding:6px 8px;font-size:11px}
  table.rincian th{background:#f0f0f0;text-align:left}
  table.rincian td.angka{text-align:right}
  table.rincian tfoot td{font-weight:700;background:#f7f7f7}
  .ttd{width:100%;margin-top:40px}
  .ttd table{width:100%}
  .ttd td{width:50%;text-align:center;font-size:11.5px;vertical-align:top}
  .ttd .garis{margin-top:56px;border-top:1px solid #1a1a1a;padding-top:4px;display:inline-block;min-width:200px}
  .catatan{margin-top:24px;font-size:10px;color:#666}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyLogo::dataUri() }}" alt="Bank NTB Syariah">
    <h1>Bank NTB Syariah</h1>
    <p>{{ $officeAddress ?? 'Alamat kantor belum diisi — lengkapi di Daftar Kantor' }}</p>
  </div>

  <div class="judul">
    <h2>NOTA DEBET</h2>
    <p>Nomor: {{ $batch->reference_number }}</p>
  </div>

  <table class="data">
    <tr><td class="label">Perihal</td><td class="sep">:</td><td>Pembayaran Bekal Cuti — {{ $batch->payer_scope === 'hc' ? 'Divisi '.$batch->division : $batch->office_name }}</td></tr>
    <tr><td class="label">Tanggal</td><td class="sep">:</td><td>{{ \Carbon\Carbon::parse($batch->created_at)->translatedFormat('l, d F Y') }}</td></tr>
  </table>

  {{--
    Nota Debet gabungan (BEDA dari lembur yang memisah Nota Debet +
    Jurnal Slip): 2 baris debet (Beban Uang Cuti = net, Beban PPh 21 =
    pajak) berpasangan lurus dengan 2 baris kredit (Rekening Pegawai
    terlampir = net, Penampungan Pajak = pajak) — total debet = total
    kredit = net + pajak = bruto, seimbang secara akuntansi.
  --}}
  <table class="jurnal">
    <thead>
      <tr><th>Akun</th><th style="text-align:right">Debet</th><th style="text-align:right">Kredit</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $batch->leave_expense_account_code }} — {{ $batch->leave_expense_account_name }}</td>
        <td class="angka">Rp{{ number_format($batch->total_net_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">—</td>
      </tr>
      <tr>
        <td>{{ $batch->tax_expense_account_code }} — {{ $batch->tax_expense_account_name }}</td>
        <td class="angka">Rp{{ number_format($batch->total_tax_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">—</td>
      </tr>
      <tr>
        <td>Terlampir (rekening pegawai — lihat lampiran)</td>
        <td class="angka">—</td>
        <td class="angka">Rp{{ number_format($batch->total_net_cents / 100, 0, ',', '.') }}</td>
      </tr>
      <tr>
        <td>{{ $batch->tax_holding_account_code }} — {{ $batch->tax_holding_account_name }}</td>
        <td class="angka">—</td>
        <td class="angka">Rp{{ number_format($batch->total_tax_cents / 100, 0, ',', '.') }}</td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td>TOTAL</td>
        <td class="angka">Rp{{ number_format($batch->total_gross_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">Rp{{ number_format($batch->total_gross_cents / 100, 0, ',', '.') }}</td>
      </tr>
    </tfoot>
  </table>

  <div class="lampiran-judul">LAMPIRAN — RINCIAN REKENING PEGAWAI</div>
  <table class="rincian">
    <thead>
      <tr><th>Pegawai</th><th>NRP</th><th>No. Rekening</th><th style="text-align:right">Bruto</th><th style="text-align:right">PPh 21</th><th style="text-align:right">Jumlah Bersih</th></tr>
    </thead>
    <tbody>
      @foreach ($items as $i)
        <tr>
          <td>{{ $i->full_name }}</td>
          <td>{{ $i->nrp }}</td>
          <td>{{ $i->bank_account_number ?? '—' }}</td>
          <td class="angka">Rp{{ number_format($i->gross_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($i->tax_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($i->net_cents / 100, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3">TOTAL</td>
        <td class="angka">Rp{{ number_format($batch->total_gross_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">Rp{{ number_format($batch->total_tax_cents / 100, 0, ',', '.') }}</td>
        <td class="angka">Rp{{ number_format($batch->total_net_cents / 100, 0, ',', '.') }}</td>
      </tr>
    </tfoot>
  </table>

  <div class="ttd">
    <table>
      <tr>
        <td>
          Dibuat oleh,<br><br>
          <span class="garis">{{ $batch->signatory_name }}</span>
        </td>
        <td>
          Disetujui oleh,<br><br>
          <span class="garis">&nbsp;</span>
        </td>
      </tr>
    </table>
  </div>

  <p class="catatan">
    Dokumen ini dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
    berdasarkan batch pembayaran {{ $batch->reference_number }}. Nota Debet ini menggabungkan
    beban uang cuti dan beban PPh 21 sekaligus — tidak ada Jurnal Slip terpisah.
  </p>
</body>
</html>
