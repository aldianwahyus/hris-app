@extends('layouts.app')

@section('judul', 'Perpustakaan Digital')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.cari{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.cari input,.cari select{padding:8px 10px;border:1px solid var(--garis);border-radius:7px;
  font-family:inherit;font-size:12.5px}
.cari input{flex:1;min-width:200px}
.cari button{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.item{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:14px;
  display:flex;flex-direction:column;gap:6px}
.item h3{font-size:13.5px;font-weight:700}
.item p{font-size:11.5px;color:var(--teks-lemah);line-height:1.5;flex:1}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis);align-self:flex-start}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:inherit;text-align:center}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px;grid-column:1/-1}
@endsection

@section('isi')
<div class="kepala">
  <h2>Perpustakaan digital</h2>
  <p>Materi pembelajaran — dokumen dan tautan referensi</p>
</div>

<form method="GET" action="{{ route('lms.library.index') }}" class="cari">
  <input type="text" name="q" value="{{ $keyword }}" placeholder="Cari judul atau deskripsi...">
  <select name="kategori">
    <option value="">Semua Kategori</option>
    @foreach ($categories as $cat)
      <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
    @endforeach
  </select>
  <button type="submit">Cari</button>
</form>

<div class="grid">
  @forelse ($items as $item)
    <div class="item">
      @if ($item->category)
        <span class="tag">{{ $item->category }}</span>
      @endif
      <h3>{{ $item->title }}</h3>
      @if ($item->course_title)
        <div style="font-size:11px;color:var(--teks-lemah)">Kursus: {{ $item->course_title }}</div>
      @endif
      <p>{{ $item->description }}</p>
      <a href="{{ route('lms.library.open', $item->id) }}" class="mini">
        {{ $item->external_url ? 'Buka Tautan' : 'Unduh' }}
      </a>
    </div>
  @empty
    <div class="kosong">Belum ada materi yang cocok.</div>
  @endforelse
</div>
@endsection
