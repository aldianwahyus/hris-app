@extends('layouts.app')

@section('judul', 'Sesi Aktif Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.daftar{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
.baris{padding:14px 16px;border-bottom:1px solid var(--garis);font-size:12.5px;
  display:flex;justify-content:space-between;align-items:center;gap:12px}
.baris:last-child{border-bottom:0}
.ua{font-weight:600;font-size:12.5px}
.info-baris{font-size:11px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks)}
.mini:hover{background:var(--latar)}
.mini.bahaya{color:var(--merah)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Sesi Aktif Saya</h2>
    <p>Perangkat yang sedang masuk ke akun Anda</p>
  </div>
  @if ($sessions->count() > 1)
    <form method="POST" action="{{ route('security-settings.revoke-others') }}" data-confirm="Keluar dari seluruh perangkat lain? Sesi Anda saat ini tidak terpengaruh.">
      @csrf
      <button type="submit" class="mini bahaya">Keluar dari Perangkat Lain</button>
    </form>
  @endif
</div>

<div class="daftar">
  @foreach ($sessions as $s)
    <div class="baris">
      <div>
        <div class="ua">{{ $s->user_agent ?? 'Perangkat tidak dikenal' }}</div>
        <div class="info-baris">{{ $s->ip_address ?? '—' }} &middot; {{ $s->last_activity_human }}</div>
      </div>
      @if ($s->is_current)
        <span class="status">Sesi Ini</span>
      @else
        <form method="POST" action="{{ route('security-settings.revoke', $s->id) }}" data-confirm="Keluar dari perangkat ini?">
          @csrf
          <button type="submit" class="mini bahaya">Cabut</button>
        </form>
      @endif
    </div>
  @endforeach
</div>
@endsection
