@extends('layouts.app')

@section('judul', 'Asesmen')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.item{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:14px;
  display:flex;flex-direction:column;gap:6px}
.item h3{font-size:13.5px;font-weight:700}
.item p{font-size:11.5px;color:var(--teks-lemah);line-height:1.5;flex:1}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.gagal{background:#FBE3E3;color:#9B2C2C}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff;
  text-align:center;text-decoration:none}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px;grid-column:1/-1}
@endsection

@section('isi')
<div class="kepala">
  <h2>Asesmen</h2>
  <p>Ujian online — skor pilihan ganda langsung terlihat, esai menunggu penilaian</p>
</div>

@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

<div class="grid">
  @forelse ($assessments as $a)
    @php $attempts = $myAttempts[$a->id] ?? collect(); $latest = $attempts->first(); @endphp
    <div class="item">
      <h3>{{ $a->title }}</h3>
      @if ($a->course_title)
        <div style="font-size:11px;color:var(--teks-lemah)">Kursus: {{ $a->course_title }}</div>
      @endif
      <p>{{ $a->description }}</p>
      <div style="font-size:11px;color:var(--teks-lemah)">
        Nilai lulus: {{ $a->passing_score }}{{ $a->duration_minutes ? ' · '.$a->duration_minutes.' menit' : '' }}
      </div>

      @if ($latest && $latest->status === 'in_progress')
        <a href="{{ route('lms.assessment.take', $latest->id) }}" class="mini">Lanjutkan Pengerjaan</a>
      @elseif ($latest && $latest->status === 'scored')
        <span class="tag {{ $latest->passed ? 'lulus' : 'gagal' }}">{{ $latest->passed ? 'Lulus' : 'Tidak Lulus' }} ({{ $latest->total_score }})</span>
        <a href="{{ route('lms.assessment.result', $latest->id) }}" class="mini">Lihat Hasil</a>
      @elseif ($latest && $latest->status === 'submitted')
        <span class="tag">Menunggu Penilaian</span>
      @else
        <form method="POST" action="{{ route('lms.assessment.start', $a->id) }}">
          @csrf
          <button type="submit" class="mini" style="width:100%">Mulai Asesmen</button>
        </form>
      @endif
    </div>
  @empty
    <div class="kosong">Belum ada asesmen yang tersedia.</div>
  @endforelse
</div>
@endsection
