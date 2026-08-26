<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Sertifikat {{ $en->certificate_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;color:#1a1a1a;margin:0}
  .bingkai{border:6px solid #1a4d2e;margin:26px;padding:48px 56px;text-align:center}
  .bingkai-dalam{border:1px solid #1a4d2e;padding:36px}
  .kop h1{font-size:14px;letter-spacing:.08em;text-transform:uppercase;margin:0 0 2px;color:#1a4d2e}
  .kop p{margin:0;font-size:10.5px;color:#444}
  .judul{font-size:22px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin:28px 0 6px;color:#1a4d2e}
  .sub{font-size:11px;color:#555;margin-bottom:24px}
  .nama{font-size:24px;font-weight:700;margin:10px 0;border-bottom:1px solid #999;display:inline-block;padding:0 24px 8px}
  .nrp{font-size:11px;color:#555;margin-bottom:20px}
  .keterangan{font-size:12.5px;line-height:1.8;margin:0 auto 20px;max-width:480px}
  .kursus{font-size:16px;font-weight:700;margin:6px 0 18px}
  table.info{margin:0 auto 24px;border-collapse:collapse;font-size:10.5px}
  table.info td{padding:2px 10px;color:#444}
  .nomor{margin-top:30px;font-size:10px;color:#666}
  .ttd{margin-top:36px;display:inline-block;text-align:center;font-size:11px}
  .ttd .garis{margin-top:44px;border-top:1px solid #1a1a1a;padding-top:4px;display:inline-block;min-width:200px}
</style>
</head>
<body>
  <div class="bingkai">
    <div class="bingkai-dalam">
      <div class="kop">
        <h1>Bank NTB Syariah</h1>
        <p>Learning Management System — Internal</p>
      </div>

      <div class="judul">Sertifikat Pelatihan</div>
      <div class="sub">Diberikan kepada</div>

      <div class="nama">{{ $en->full_name }}</div>
      <div class="nrp">NRP {{ $en->nrp }}</div>

      <div class="keterangan">
        Telah dinyatakan <strong>LULUS</strong> mengikuti pelatihan
      </div>
      <div class="kursus">{{ $en->course_title }}</div>

      <table class="info">
        <tr>
          <td>Jadwal Kelas</td>
          <td>: {{ $en->batch_code }}</td>
          <td style="width:24px"></td>
          <td>Periode</td>
          <td>: {{ date('j M Y', strtotime($en->start_date)) }} – {{ date('j M Y', strtotime($en->end_date)) }}</td>
        </tr>
        @if ($en->score !== null)
          <tr>
            <td>Nilai</td>
            <td>: {{ $en->score }}</td>
          </tr>
        @endif
      </table>

      <div class="ttd">
        <div>{{ date('j F Y', strtotime($en->completed_at)) }}</div>
        <div class="garis">Divisi Human Capital</div>
      </div>

      <div class="nomor">Nomor Sertifikat: {{ $en->certificate_number }}</div>
    </div>
  </div>
</body>
</html>
