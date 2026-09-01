@extends('layouts.app')

@section('judul', 'Notifikasi')
@section('peran', 'Semua Pengguna')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.daftar{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
.baris{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;
  padding:14px 16px;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.baris.belum-dibaca{background:var(--hijau-muda)}
.waktu{font-size:10.5px;color:var(--teks-lemah);margin-top:4px}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.titik{display:inline-block;width:7px;height:7px;border-radius:99px;background:var(--hijau);flex-shrink:0;margin-top:5px}
.halaman{display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--teks-lemah)}
.halaman a{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);color:var(--teks);font-weight:600}
.halaman .nonaktif{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);color:var(--teks-lemah);opacity:.5}
@endsection

@section('isi')
<div class="kepala">
  <h2>Notifikasi</h2>
  <p>Riwayat pemberitahuan sistem HCIS untuk akun Anda</p>
</div>

<div class="daftar">
  @forelse ($notifications as $n)
    <div class="baris{{ $n->read_at === null ? ' belum-dibaca' : '' }}">
      <div style="display:flex;gap:10px;flex:1">
        @if ($n->read_at === null)
          <span class="titik"></span>
        @else
          <span style="width:7px"></span>
        @endif
        <div>
          {{ $n->data['message'] ?? 'Notifikasi' }}
          <div class="waktu">{{ $n->created_at->translatedFormat('d F Y, H:i') }}</div>
        </div>
      </div>
      @if ($n->read_at === null)
        <form method="POST" action="{{ route('notifikasi.baca', $n->id) }}">
          @csrf
          <button type="submit" style="border:none;background:none;color:var(--hijau-tua);font-size:11.5px;font-weight:600;cursor:pointer">Tandai dibaca</button>
        </form>
      @endif
    </div>
  @empty
    <div class="kosong">Belum ada notifikasi.</div>
  @endforelse
</div>

@if ($notifications->hasPages())
  <div class="halaman">
    <span>Halaman {{ $notifications->currentPage() }} dari {{ $notifications->lastPage() }}</span>
    <span>
      @if ($notifications->onFirstPage())
        <span class="nonaktif">← Sebelumnya</span>
      @else
        <a href="{{ $notifications->previousPageUrl() }}">← Sebelumnya</a>
      @endif
      @if ($notifications->hasMorePages())
        <a href="{{ $notifications->nextPageUrl() }}">Berikutnya →</a>
      @else
        <span class="nonaktif">Berikutnya →</span>
      @endif
    </span>
  </div>
@endif
@endsection
