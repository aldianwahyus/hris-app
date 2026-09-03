@extends('layouts.app')

@section('judul', 'Pipeline — '.$posting->title)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.melamar{background:var(--emas-muda);color:#7A5F0B}
.status.seleksi_berkas{background:#DCEAFB;color:#1D4E89}
.status.wawancara{background:#EAE2F8;color:#5B2A9E}
.status.penawaran{background:#FDEBD3;color:#8A5A00}
.status.diterima{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditolak{background:#EDEDED;color:#6B6B6B}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $posting->title }}</h2>
  <p>Pipeline kandidat untuk lowongan ini</p>
</div>

@php
  $labelStatus = ['melamar' => 'Melamar', 'seleksi_berkas' => 'Seleksi Berkas', 'wawancara' => 'Wawancara', 'penawaran' => 'Penawaran', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];
@endphp

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kandidat</th><th>Email</th><th>Tahap</th><th>Melamar</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($applications as $a)
        <tr>
          <td>{{ $a->full_name }}</td>
          <td>{{ $a->email }}</td>
          <td><span class="status {{ $a->status }}">{{ $labelStatus[$a->status] ?? $a->status }}</span></td>
          <td class="angka">{{ date('j M Y', strtotime($a->applied_at)) }}</td>
          <td><a href="{{ route('admin.recruitment-application-show', $a->id) }}" class="tautan">Buka</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Belum ada kandidat yang melamar posisi ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
