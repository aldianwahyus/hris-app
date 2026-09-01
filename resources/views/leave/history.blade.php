@extends('layouts.app')

@section('judul', 'Riwayat Cuti Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.daftar{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
.baris{padding:14px 16px;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.baris-atas{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.j{font-weight:600;font-size:12.5px}
.s{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending, .status.pending_pimpinan{background:var(--emas-muda);color:#7A5F0B}
.status.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected, .status.expired{background:var(--merah-muda);color:var(--merah)}
.status.cancelled{background:#EDEDED;color:#6B6B6B}
.alasan{margin-top:8px;padding:9px 11px;background:var(--merah-muda);border-radius:7px;font-size:11.5px;color:#7A1F1F;line-height:1.5}
.aksi-baris{margin-top:10px}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.halaman{display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--teks-lemah)}
.halaman a{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);color:var(--teks);font-weight:600}
.halaman .nonaktif{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);color:var(--teks-lemah);opacity:.5}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Riwayat Cuti Saya</h2>
    <p>Seluruh pengajuan cuti yang pernah Anda kirim</p>
  </div>
  <a href="{{ route('leave.create') }}" class="btn" style="padding:8px 14px">Ajukan Cuti</a>
</div>

<div class="daftar">
  @forelse ($requests as $r)
    <div class="baris">
      <div class="baris-atas">
        <div>
          <div class="j">{{ $r->leave_type === 'CT' ? 'Cuti tahunan' : $r->leave_type }} — {{ rtrim(rtrim(number_format($r->total_days, 1, ',', '.'), '0'), ',') }} hari</div>
          <div class="s angka">{{ date('j M', strtotime($r->start_date)) }}–{{ date('j M Y', strtotime($r->end_date)) }} &middot; {{ $r->request_number }}</div>
        </div>
        <span class="status {{ $r->status }}">
          {{ ['pending' => 'Menunggu Atasan', 'pending_pimpinan' => 'Menunggu Pimpinan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'][$r->status] ?? $r->status }}
        </span>
      </div>

      @if ($r->status === 'rejected' && ! empty($r->decision_note))
        <div class="alasan">Alasan penolakan: {{ $r->decision_note }}</div>
      @endif

      @if ($r->status === 'pending')
        <div class="aksi-baris">
          <form method="POST" action="{{ route('leave.cancel', $r->id) }}" onsubmit="return confirm('Batalkan pengajuan cuti ini?')">
            @csrf
            <button type="submit" class="btn luar" style="padding:6px 12px">Batalkan</button>
          </form>
        </div>
      @endif
    </div>
  @empty
    <div class="kosong">Belum ada pengajuan cuti.</div>
  @endforelse
</div>

@if ($requests->hasPages())
  <div class="halaman">
    <span>Halaman {{ $requests->currentPage() }} dari {{ $requests->lastPage() }}</span>
    <span>
      @if ($requests->onFirstPage())
        <span class="nonaktif">← Sebelumnya</span>
      @else
        <a href="{{ $requests->previousPageUrl() }}">← Sebelumnya</a>
      @endif
      @if ($requests->hasMorePages())
        <a href="{{ $requests->nextPageUrl() }}">Berikutnya →</a>
      @else
        <span class="nonaktif">Berikutnya →</span>
      @endif
    </span>
  </div>
@endif
@endsection
