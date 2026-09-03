@extends('layouts.app')

@section('judul', 'Tiket Bantuan Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.daftar{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
.baris{padding:14px 16px;border-bottom:1px solid var(--garis);font-size:12.5px;display:block;color:inherit;text-decoration:none}
.baris:last-child{border-bottom:0}
.baris:hover{background:var(--latar)}
.baris-atas{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.j{font-weight:600;font-size:12.5px}
.s{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.terbuka{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditutup{background:#EDEDED;color:#6B6B6B}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.halaman{display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--teks-lemah)}
.halaman a{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);color:var(--teks);font-weight:600}
.halaman .nonaktif{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);color:var(--teks-lemah);opacity:.5}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Tiket Bantuan Saya</h2>
    <p>Seluruh tiket bantuan yang pernah Anda ajukan ke HC</p>
  </div>
  <a href="{{ route('helpdesk.create') }}" class="btn" style="padding:8px 14px">Ajukan Tiket</a>
</div>

<div class="daftar">
  @forelse ($tickets as $t)
    <a href="{{ route('helpdesk.show', $t->id) }}" class="baris">
      <div class="baris-atas">
        <div>
          <div class="j">{{ $t->subject }}</div>
          <div class="s angka">{{ $t->ticket_number }} &middot; {{ date('j M Y', strtotime($t->created_at)) }}</div>
        </div>
        <span class="status {{ $t->status }}">
          {{ ['terbuka' => 'Terbuka', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'][$t->status] ?? $t->status }}
        </span>
      </div>
    </a>
  @empty
    <div class="kosong">Belum ada tiket bantuan.</div>
  @endforelse
</div>

@if ($tickets->hasPages())
  <div class="halaman">
    <span>Halaman {{ $tickets->currentPage() }} dari {{ $tickets->lastPage() }}</span>
    <span>
      @if ($tickets->onFirstPage())
        <span class="nonaktif">← Sebelumnya</span>
      @else
        <a href="{{ $tickets->previousPageUrl() }}">← Sebelumnya</a>
      @endif
      @if ($tickets->hasMorePages())
        <a href="{{ $tickets->nextPageUrl() }}">Berikutnya →</a>
      @else
        <span class="nonaktif">Berikutnya →</span>
      @endif
    </span>
  </div>
@endif
@endsection
