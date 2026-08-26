@extends('layouts.app')

@section('judul', 'Kompetensi Kursus')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.pilihan{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.pilihan label{display:flex;align-items:center;gap:6px;font-size:12.5px;
  padding:7px 12px;border:1px solid var(--garis);border-radius:99px;cursor:pointer}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.kosong{color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Kompetensi — {{ $course->title }}</h2>
  <p>Tandai kompetensi apa saja yang dikembangkan kursus ini — dasar rekomendasi otomatis berbasis gap</p>
</div>

<div class="kartu">
  @if ($competencies->isEmpty())
    <div class="kosong">Belum ada kompetensi aktif — tambahkan lewat halaman Kompetensi.</div>
  @else
    <form method="POST" action="{{ route('lms.admin.competencies.map-course.store', $course->id) }}">
      @csrf
      <div class="pilihan">
        @foreach ($competencies as $c)
          <label>
            <input type="checkbox" name="competency_ids[]" value="{{ $c->id }}" @checked(in_array($c->id, $mapped, true))>
            {{ $c->name }}
          </label>
        @endforeach
      </div>
      <button type="submit" class="mini">Simpan</button>
    </form>
  @endif
</div>
@endsection
