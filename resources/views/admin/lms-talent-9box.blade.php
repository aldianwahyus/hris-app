@extends('layouts.app')

@section('judul', 'Talent Management — 9-Box')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.grid9{display:grid;grid-template-columns:100px repeat(3,1fr);gap:6px;margin-bottom:20px}
.sumbu{display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.04em;color:var(--teks-lemah);text-align:center}
.sel{background:var(--putih);border:1px solid var(--garis);border-radius:8px;padding:8px;min-height:110px}
.sel h4{font-size:10px;font-weight:700;color:var(--teks-lemah);margin-bottom:6px;text-transform:uppercase}
.org{display:block;font-size:11.5px;padding:3px 0;color:inherit;text-decoration:none}
.org:hover{text-decoration:underline}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none}
.mini:hover{background:var(--latar)}
.kosong{padding:20px;text-align:center;color:var(--teks-lemah);font-size:12px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Talent management — 9-box</h2>
  <p>Performance × Potential (BRD §5.6) — performance/potential input manual HC, sengaja BUKAN hasil sistem appraisal otomatis</p>
</div>

<div class="grid9">
  <div></div>
  <div class="sumbu">Potential Rendah</div>
  <div class="sumbu">Potential Sedang</div>
  <div class="sumbu">Potential Tinggi</div>

  @foreach (['tinggi' => 'Performance Tinggi', 'sedang' => 'Performance Sedang', 'rendah' => 'Performance Rendah'] as $perfKey => $perfLabel)
    <div class="sumbu">{{ $perfLabel }}</div>
    @foreach (['rendah', 'sedang', 'tinggi'] as $potKey)
      <div class="sel">
        <h4>{{ count($grid[$perfKey][$potKey] ?? []) }} pegawai</h4>
        @foreach ($grid[$perfKey][$potKey] ?? [] as $e)
          <a href="{{ route('lms.admin.talent.show', $e->id) }}" class="org">{{ $e->full_name }}</a>
        @endforeach
      </div>
    @endforeach
  @endforeach
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Belum Dinilai</th><th>NRP</th><th>Jabatan</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($unassessed as $e)
        <tr>
          <td>{{ $e->full_name }}</td>
          <td class="angka">{{ $e->nrp }}</td>
          <td>{{ $e->position_name }}</td>
          <td><a href="{{ route('lms.admin.talent.show', $e->id) }}" class="mini">Nilai</a></td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Semua pegawai sudah dinilai.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
