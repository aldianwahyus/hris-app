@extends('layouts.app')

@section('judul', 'Lencana Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.skor{font-size:28px;font-weight:700;margin-bottom:16px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.badge{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:14px;text-align:center}
.badge .ic{font-size:28px;display:block;margin-bottom:6px}
.badge h4{font-size:12.5px;font-weight:700}
.badge p{font-size:10.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:12px}
.kartu h3{font-size:13px;font-weight:700;margin-bottom:4px}
.kartu p{font-size:11.5px;color:var(--teks-lemah);margin-bottom:8px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.kosong{color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Lencana saya</h2>
  <p>Poin didapat otomatis dari kelulusan pelatihan/asesmen, dan menyelesaikan challenge</p>
</div>

@if (session('sukses'))
  <div class="pesan sukses">{{ session('sukses') }}</div>
@endif
@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

<div class="skor">{{ $totalPoints }} poin</div>

<div class="grid">
  @forelse ($myBadges as $b)
    <div class="badge">
      <span class="ic">{{ $b->icon ?? '🏅' }}</span>
      <h4>{{ $b->name }}</h4>
      <p>{{ $b->description }}</p>
    </div>
  @empty
    <div class="kosong">Anda belum punya lencana.</div>
  @endforelse
</div>

<h3 style="font-size:14px;font-weight:700;margin-bottom:10px">Challenge aktif</h3>
@forelse ($activeChallenges as $c)
  @php $status = $myParticipation[$c->id] ?? null; @endphp
  <div class="kartu">
    <h3>{{ $c->title }} <span class="tag">{{ $c->points_reward }} poin</span></h3>
    <p>{{ $c->description }}</p>
    @if ($status === 'completed')
      <span class="tag">Selesai</span>
    @elseif ($status === 'joined')
      <span class="tag">Sedang Diikuti</span>
    @else
      <form method="POST" action="{{ route('lms.challenge.join', $c->id) }}">
        @csrf
        <button type="submit" class="mini">Ikuti Challenge</button>
      </form>
    @endif
  </div>
@empty
  <div class="kosong">Tidak ada challenge aktif saat ini.</div>
@endforelse
@endsection
