<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $row->spkl_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:12px;color:#1a1a1a;margin:32px}
  .kop{text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:18px}
  .kop h1{font-size:15px;margin:0 0 2px}
  .kop p{margin:0;font-size:11px;color:#444}
  .judul{text-align:center;margin-bottom:4px}
  .judul h2{font-size:13px;text-decoration:underline;margin:0}
  .judul p{margin:2px 0 0;font-size:11px}
  table.data{width:100%;border-collapse:collapse;margin:18px 0}
  table.data td{padding:4px 6px;vertical-align:top;font-size:11.5px}
  table.data td.label{width:180px;color:#333}
  table.data td.sep{width:14px}
  .isi{margin:16px 0;font-size:11.5px;line-height:1.7;text-align:justify}
  table.rincian{width:100%;border-collapse:collapse;margin:14px 0}
  table.rincian th,table.rincian td{border:1px solid #999;padding:6px 8px;font-size:11px}
  table.rincian th{background:#f0f0f0;text-align:left}
  table.rincian td.angka{text-align:right}
  .ttd{width:100%;margin-top:48px}
  .ttd table{width:100%}
  .ttd td{width:50%;text-align:center;font-size:11.5px;vertical-align:top}
  .ttd .garis{margin-top:56px;border-top:1px solid #1a1a1a;padding-top:4px;display:inline-block;min-width:200px}
  .catatan{margin-top:24px;font-size:10px;color:#666}
  .status-lunas{text-align:center;margin:10px 0 18px;padding:6px 10px;border:1px solid #1a7a3a;
    color:#1a7a3a;font-weight:bold;font-size:11.5px;letter-spacing:.04em}
</style>
</head>
<body>
  <div class="kop">
    <h1>Bank NTB Syariah</h1>
    <p>Kantor Pusat — Jl. Pejanggik No. 30 Mataram</p>
  </div>

  <div class="judul">
    <h2>SURAT PERINTAH KERJA LEMBUR (SPKL)</h2>
    <p>Nomor: {{ $row->spkl_number }}</p>
  </div>

  @if ($row->status === 'disbursed')
    <div class="status-lunas">
      SUDAH DICAIRKAN — Referensi Pembayaran: {{ $row->disbursement_reference }}
      @if ($row->disbursed_at)
        ({{ \Carbon\Carbon::parse($row->disbursed_at)->translatedFormat('d F Y') }})
      @endif
    </div>
  @endif

  <table class="data">
    <tr><td class="label">Nama Pegawai</td><td class="sep">:</td><td>{{ $row->full_name }}</td></tr>
    <tr><td class="label">NRP</td><td class="sep">:</td><td>{{ $row->nrp }}</td></tr>
    <tr><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $row->position_name }} (PG {{ $row->person_grade }})</td></tr>
    <tr><td class="label">Unit Kerja</td><td class="sep">:</td><td>{{ $row->office_name }}</td></tr>
    <tr><td class="label">Jenis Lembur</td><td class="sep">:</td><td>{{ $overtimeTypeLabel }}</td></tr>
    <tr><td class="label">Tanggal Pelaksanaan</td><td class="sep">:</td><td>{{ \Carbon\Carbon::parse($row->work_date)->translatedFormat('l, d F Y') }}</td></tr>
  </table>

  <div class="isi">
    Dengan ini menerangkan bahwa pegawai tersebut di atas telah melaksanakan tugas lembur
    sesuai jenis dan tanggal di atas, berdasarkan bukti kehadiran yang tercatat pada sistem
    absensi, dan telah disetujui oleh pejabat berwenang sebagaimana tercantum di bawah ini.
  </div>

  <table class="rincian">
    <thead>
      <tr><th>Uraian</th><th style="text-align:right">Jumlah</th></tr>
    </thead>
    <tbody>
      <tr><td>Jam lembur (realisasi absensi)</td><td class="angka">{{ number_format((float) $row->payable_hours, 1, ',', '.') }} jam</td></tr>
      <tr><td>Nilai lembur</td><td class="angka">Rp{{ number_format(((int) $row->amount_cents) / 100, 0, ',', '.') }}</td></tr>
    </tbody>
  </table>

  <div class="ttd">
    <table>
      <tr>
        <td>
          Pegawai yang bersangkutan,<br><br>
          <span class="garis">{{ $row->full_name }}</span>
        </td>
        <td>
          Menyetujui,<br><br>
          <span class="garis">{{ $row->approver_name ?? '—' }}</span>
        </td>
      </tr>
    </table>
  </div>

  <p class="catatan">
    Dokumen ini dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
    dan sah tanpa tanda tangan basah selama status pengajuan tercatat "disetujui" atau "sudah dicairkan" pada sistem.
  </p>
</body>
</html>
