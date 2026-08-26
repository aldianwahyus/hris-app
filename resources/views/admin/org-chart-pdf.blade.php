<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Struktur Organisasi — {{ $judulUnit }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:11px;color:#1a1a1a;margin:28px}
  .kop{text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:16px}
  .kop h1{font-size:14px;margin:0 0 2px}
  .kop p{margin:0;font-size:10px;color:#444}
  .judul{text-align:center;margin-bottom:16px}
  .judul h2{font-size:12.5px;text-decoration:underline;margin:0}
  table.bagan{width:100%;border-collapse:collapse}
  table.bagan th{background:#f0f0f0;text-align:left;padding:6px 8px;font-size:10.5px;border:1px solid #999}
  table.bagan td{padding:5px 8px;font-size:10.5px;border:1px solid #ccc;vertical-align:top}
  .kosong{padding:20px;text-align:center;color:#666}
</style>
</head>
<body>
  <div class="kop">
    <h1>Bank NTB Syariah</h1>
    <p>Kantor Pusat — Jl. Pejanggik No. 30 Mataram</p>
  </div>

  <div class="judul">
    <h2>STRUKTUR ORGANISASI — {{ strtoupper($judulUnit) }}</h2>
  </div>

  @if ($tree->isEmpty())
    <div class="kosong">Belum ada pegawai pada unit ini.</div>
  @else
    <table class="bagan">
      <thead>
        <tr><th style="width:40%">Nama (berjenjang sesuai Atasan Langsung)</th><th>Jabatan</th><th>NRP</th></tr>
      </thead>
      <tbody>
        @foreach ($tree as $node)
          @include('admin._org-chart-pdf-row', ['node' => $node, 'depth' => 0])
        @endforeach
      </tbody>
    </table>
  @endif
</body>
</html>
