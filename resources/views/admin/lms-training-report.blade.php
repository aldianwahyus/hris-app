@extends('layouts.app')

@section('judul', 'Laporan Pelatihan')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px}
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
  <h2>Laporan pelatihan</h2>
  <a href="{{ route('lms.admin.analytics.training-report.export') }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kursus</th><th>Jadwal</th><th>Terdaftar</th><th>Disetujui</th><th>Lulus</th><th>Tidak Lulus</th><th>Completion Rate</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td>{{ $r->course_title }}</td>
          <td class="angka">{{ $r->batch_code }} ({{ date('j M Y', strtotime($r->start_date)) }})</td>
          <td class="angka">{{ $r->terdaftar }}</td>
          <td class="angka">{{ $r->disetujui }}</td>
          <td class="angka">{{ $r->lulus }}</td>
          <td class="angka">{{ $r->tidak_lulus }}</td>
          <td class="angka">{{ $r->completion_rate !== null ? $r->completion_rate.'%' : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="kosong">Belum ada data.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
