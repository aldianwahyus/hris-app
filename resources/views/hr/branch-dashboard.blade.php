@extends('layouts.app')

@section('judul', 'Dashboard Cabang')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:11px;margin-bottom:16px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:14px}
.ring .a{font-size:25px;font-weight:800;letter-spacing:-.03em}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.baris{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.kosong{padding:16px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
@php
  $statusLabel = ['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsourcing'];
  $genderLabel = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
@endphp

<div class="kepala">
  <h2>Dashboard Cabang — {{ $office->name }}</h2>
  <p>Lingkup kantor Anda (OFFICE)</p>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $totalPegawai }}</div>
    <div class="l">Total pegawai</div>
  </div>
  @foreach ($employmentStatusBreakdown as $s)
    <div class="ring">
      <div class="a angka">{{ $s->jumlah }}</div>
      <div class="l">Pegawai {{ $statusLabel[$s->employment_status] ?? $s->employment_status }}</div>
    </div>
  @endforeach
  @foreach ($genderBreakdown as $g)
    <div class="ring">
      <div class="a angka">{{ $g->jumlah }}</div>
      <div class="l">Pegawai {{ $genderLabel[$g->gender] ?? ($g->gender ?? 'Tidak diisi') }}</div>
    </div>
  @endforeach
</div>

<div class="kartu">
  <div class="kartu-judul">Ulang tahun {{ $upcomingWindowMonths }} bulan ke depan</div>
  @forelse ($upcomingBirthdays as $b)
    <div class="baris">
      <span>{{ $b->full_name }} <span class="angka" style="color:var(--teks-lemah)">({{ $b->nrp }})</span></span>
      <span class="angka">{{ $b->tanggal->format('d M Y') }}</span>
    </div>
  @empty
    <div class="kosong">Tidak ada pegawai berulang tahun dalam periode ini.</div>
  @endforelse
</div>
@endsection
