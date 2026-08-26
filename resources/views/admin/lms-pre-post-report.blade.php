@extends('layouts.app')

@section('judul', 'Laporan Pre/Post Test')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
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
.naik{color:var(--hijau-tua)}
.turun{color:#9B2C2C}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Laporan pre/post test</h2>
    <p>Level 2 — Peningkatan knowledge (BRD §5.5), dari Assessment Center bertipe pre_test/post_test</p>
  </div>
  <a href="{{ route('lms.admin.evaluations.pre-post-report.export') }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Skor Pre-Test</th><th>Skor Post-Test</th><th>Selisih</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td>{{ $r->full_name }}</td>
          <td class="angka">{{ $r->pre_score ?? '—' }}</td>
          <td class="angka">{{ $r->post_score ?? '—' }}</td>
          <td class="angka {{ $r->delta !== null ? ($r->delta >= 0 ? 'naik' : 'turun') : '' }}">
            {{ $r->delta !== null ? ($r->delta >= 0 ? '+' : '').$r->delta : '—' }}
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada data pre/post test.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
