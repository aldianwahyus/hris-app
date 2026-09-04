@extends('layouts.app')

@section('judul', 'Report Builder')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.daftar{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.kartu{display:block;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);
  padding:16px;text-decoration:none;color:var(--teks);transition:.12s}
.kartu:hover{border-color:var(--hijau);background:var(--hijau-muda)}
.kartu h3{font-size:14px;font-weight:700;margin-bottom:4px}
.kartu p{font-size:12px;color:var(--teks-lemah)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Report Builder</h2>
  <p>Pilih subjek laporan, kolom, dan filter, lalu unduh sebagai CSV atau PDF.</p>
</div>

<div class="daftar">
  @foreach ($subjects as $subject)
    <a href="{{ route('hr.report-builder.show', $subject->key()) }}" class="kartu">
      <h3>{{ $subject->label() }}</h3>
      <p>{{ count($subject->columns()) }} kolom tersedia</p>
    </a>
  @endforeach
</div>
@endsection
