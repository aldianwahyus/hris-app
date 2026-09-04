@extends('layouts.app')

@section('judul', 'Report Builder — '.$subject->label())
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.balik{font-size:12px;color:var(--teks-lemah);text-decoration:none;display:inline-block;margin-bottom:10px}
.balik:hover{text-decoration:underline}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:10px}
.kolom-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px}
.kolom-grid label{display:flex;align-items:center;gap:7px;font-size:12.5px;cursor:pointer}
.filter-grid{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end}
.filter-grid div{display:flex;flex-direction:column;gap:4px}
.filter-grid label{font-size:11.5px;font-weight:600;color:var(--teks-lemah)}
.filter-grid input,.filter-grid select{padding:7px 10px;border:1px solid var(--garis);border-radius:7px;
  font-family:inherit;font-size:12.5px}
.aksi{display:flex;gap:8px;margin-top:16px}
.utama{padding:9px 18px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.utama:hover{background:var(--hijau-tua)}
.utama.sekunder{background:var(--putih);color:var(--teks);border-color:var(--garis)}
.utama.sekunder:hover{background:var(--latar)}
@endsection

@section('isi')
<a href="{{ route('hr.report-builder.index') }}" class="balik">&larr; Semua Subjek Laporan</a>
<div class="kepala">
  <h2>{{ $subject->label() }}</h2>
  <p>Pilih kolom yang ingin ditampilkan, atur filter bila perlu, lalu unduh.</p>
</div>

@if (session('gagal'))
  <div class="kartu" style="border-color:var(--merah);background:var(--merah-muda)">{{ session('gagal') }}</div>
@endif

<form method="GET" action="{{ route('hr.report-builder.download', $subject->key()) }}">
  <div class="kartu">
    <h3>Kolom</h3>
    <div class="kolom-grid">
      @foreach ($subject->columns() as $column)
        <label>
          <input type="checkbox" name="columns[]" value="{{ $column->key }}" checked>
          {{ $column->label }}
        </label>
      @endforeach
    </div>
  </div>

  <div class="kartu">
    <h3>Filter</h3>
    <div class="filter-grid">
      <div>
        <label>Dari Tanggal</label>
        <input type="date" name="start">
      </div>
      <div>
        <label>Sampai Tanggal</label>
        <input type="date" name="end">
      </div>
      @if ($subject->statusOptions() !== [])
        <div>
          <label>Status</label>
          <select name="status">
            <option value="">Semua Status</option>
            @foreach ($subject->statusOptions() as $value => $label)
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      @endif
    </div>
  </div>

  <div class="aksi">
    <button type="submit" name="format" value="csv" class="utama">Unduh CSV</button>
    <button type="submit" name="format" value="pdf" class="utama sekunder">Unduh PDF</button>
  </div>
</form>
@endsection
