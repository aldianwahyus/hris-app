@extends('layouts.app')

@section('judul', 'Kelola Survei')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#FAFCFB}
.jenis{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.draft{background:#EDEDED;color:#6B6B6B}
.status.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.selesai{background:#DCEAFB;color:#1D4E89}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Kelola Survei</h2>
    <p>Survei keterlibatan (eNPS/pulse) dalam lingkup kewenangan Anda</p>
  </div>
  <a href="{{ route('admin.survey-create') }}" class="btn">+ Buat Survei</a>
</div>

@php $labelJenis = ['enps' => 'eNPS', 'pulse' => 'Pulse', 'kustom' => 'Kustom']; @endphp
@php $labelStatus = ['draft' => 'Draf', 'aktif' => 'Aktif', 'selesai' => 'Selesai']; @endphp

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Jenis</th><th>Lingkup</th><th>Periode</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($surveys as $s)
        <tr>
          <td>{{ $s->title }}</td>
          <td><span class="jenis">{{ $labelJenis[$s->type] ?? $s->type }}</span></td>
          <td>{{ $s->scope === 'bank_wide' ? 'Seluruh Bank' : 'Kantor' }}</td>
          <td class="angka">{{ date('j M', strtotime($s->start_date)) }}–{{ date('j M Y', strtotime($s->end_date)) }}</td>
          <td><span class="status {{ $s->status }}">{{ $labelStatus[$s->status] ?? $s->status }}</span></td>
          <td><a href="{{ route('admin.survey-show', $s->id) }}" class="tautan">Buka</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Belum ada survei.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
