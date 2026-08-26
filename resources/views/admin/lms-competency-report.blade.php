@extends('layouts.app')

@section('judul', 'Laporan Kompetensi')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px;flex-shrink:0}
.ekspor:hover{background:var(--hijau-tua)}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Laporan kompetensi</h2>
    <p style="font-size:12.5px;color:var(--teks-lemah)">Rata-rata level kompetensi vs level wajib per jabatan</p>
  </div>
  <a href="{{ route('lms.admin.analytics.competency-report.export') }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Jabatan</th><th>Kompetensi</th><th>Level Wajib</th><th>Rata-rata Level Saat Ini</th><th>Gap</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td>{{ $r->position_name }}</td>
          <td>{{ $r->competency_name }}</td>
          <td class="angka">{{ $r->required_level }}</td>
          <td class="angka">{{ $r->avg_current_level ?? '—' }}</td>
          <td class="angka">{{ $r->avg_gap !== null ? $r->avg_gap : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada peta kompetensi jabatan.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
