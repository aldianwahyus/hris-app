@extends('layouts.app')

@section('judul', 'Sesi Live & Mentoring')
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
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis);align-self:flex-start}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff;
  text-align:center;text-decoration:none}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px;grid-column:1/-1}
@endsection

@section('isi')
<div class="kepala">
  <h2>Sesi live &amp; mentoring</h2>
  <p>Webinar, coaching, dan mentoring — daftar untuk mendapat tautan rapat</p>
</div>

@if (session('sukses'))
  <div class="pesan sukses">{{ session('sukses') }}</div>
@endif
@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

<div class="grid">
  @forelse ($sessions as $s)
    @php $status = $myRegistrations[$s->id] ?? null; @endphp
    <div class="item">
      <span class="tag">{{ $s->session_type }}</span>
      <h3>{{ $s->title }}</h3>
      <div style="font-size:11px;color:var(--teks-lemah)">
        {{ date('j M Y H:i', strtotime($s->scheduled_at)) }}
        @if ($s->facilitator_name) · {{ $s->facilitator_name }} @endif
      </div>
      <p>{{ $s->description }}</p>

      @if ($status !== null)
        <span class="tag">{{ $status === 'attended' ? 'Hadir' : 'Terdaftar' }}</span>
        @if ($s->meeting_url)
          <a href="{{ $s->meeting_url }}" class="mini" target="_blank" rel="noopener">Buka Tautan Rapat</a>
        @endif
      @else
        <form method="POST" action="{{ route('lms.live-sessions.register', $s->id) }}">
          @csrf
          <button type="submit" class="mini" style="width:100%">Daftar</button>
        </form>
      @endif

      @if ($s->recording_url)
        <a href="{{ $s->recording_url }}" class="mini" target="_blank" rel="noopener">Lihat Rekaman</a>
      @endif
    </div>
  @empty
    <div class="kosong">Belum ada sesi terjadwal.</div>
  @endforelse
</div>
@endsection
