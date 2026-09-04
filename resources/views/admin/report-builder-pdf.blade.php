<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan {{ $subject->label() }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:10px;color:#1a1a1a;margin:24px}
  .kop{text-align:center;margin-bottom:6px}
  .kop img{height:32px}
  .judul{text-align:center;font-weight:700;font-size:13px;margin:8px 0 2px}
  .sub{text-align:center;font-size:10px;color:#555;margin-bottom:14px}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid #999;padding:5px 7px;font-size:9.5px;text-align:left}
  th{background:#f0f0f0}
  .kosong{text-align:center;padding:16px;color:#777}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyProfile::logoDataUri() }}" alt="Bank NTB Syariah">
  </div>
  <div class="judul">Laporan {{ $subject->label() }}</div>
  <div class="sub">Dihasilkan {{ now()->translatedFormat('d F Y H:i') }} WITA</div>

  <table>
    <thead>
      <tr>
        @foreach ($columns as $column)
          <th>{{ $column->label }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          @foreach ($columns as $column)
            <td>{{ $row->{$column->key} ?? '—' }}</td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td colspan="{{ count($columns) }}" class="kosong">Tidak ada data pada filter ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
