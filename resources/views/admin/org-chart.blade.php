@extends('layouts.app')

@section('judul', 'Struktur Organisasi')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.kartu-unit{display:block;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);
  padding:14px 16px;transition:.12s}
.kartu-unit:hover{border-color:var(--hijau);background:var(--hijau-muda)}
.kartu-unit .nm{font-weight:700;font-size:13.5px;margin-bottom:3px}
.kartu-unit .tp{font-size:10.5px;color:var(--teks-lemah);text-transform:uppercase;letter-spacing:.05em}
.grup-kp{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.grup-kp h3{font-size:13.5px;font-weight:700;margin-bottom:10px}
.grup-kp .grid{margin-top:0}
@endsection

@section('isi')
<div class="kepala">
  <h2>Struktur organisasi</h2>
  <p>Pilih kantor atau unit yang ingin ditampilkan bagannya</p>
</div>

@php $cabang = $offices->where('office_type', '!=', 'head_office'); @endphp

<div class="grid" style="margin-bottom:16px">
  @foreach ($cabang as $o)
    <a href="{{ route('org-chart.show', $o->id) }}" class="kartu-unit">
      <div class="nm">{{ $o->name }}</div>
      <div class="tp">{{ str_replace('_', ' ', $o->office_type) }}</div>
    </a>
  @endforeach
</div>

@if ($headOfficeId !== null)
  <div class="grup-kp">
    <h3>Kantor Pusat — per Divisi</h3>
    <div class="grid">
      @foreach ($divisions as $d)
        <a href="{{ route('org-chart.show', ['officeId' => $headOfficeId, 'divisi' => $d]) }}" class="kartu-unit">
          <div class="nm">{{ $d }}</div>
          <div class="tp">Divisi</div>
        </a>
      @endforeach
      @if ($hasUndividedHeadOfficeStaff)
        <a href="{{ route('org-chart.show', $headOfficeId) }}" class="kartu-unit">
          <div class="nm">Belum Ada Divisi</div>
          <div class="tp">Kantor Pusat</div>
        </a>
      @endif
    </div>
  </div>
@endif
@endsection
