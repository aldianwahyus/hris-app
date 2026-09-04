@extends('layouts.app')

@section('judul', 'Detail Pengaduan')
@section('peran', 'HR Approver')

@section('gaya')
.balik{font-size:12px;color:var(--teks-lemah);text-decoration:none;display:inline-block;margin-bottom:10px}
.balik:hover{text-decoration:underline}
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.baru{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris{display:flex;gap:8px;font-size:12.5px;padding:6px 0;border-bottom:1px solid var(--garis)}
.baris:last-child{border-bottom:0}
.baris .l{width:140px;color:var(--teks-lemah);flex-shrink:0}
.uraian{white-space:pre-wrap;font-size:12.5px;line-height:1.6;padding:12px 0}
textarea{width:100%;max-width:520px;border:1px solid var(--garis);border-radius:7px;padding:9px 11px;
  font-family:inherit;font-size:12.5px;resize:vertical;min-height:90px}
.utama{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff;margin-top:10px}
.utama:hover{background:var(--hijau-tua)}
@endsection

@section('isi')
<a href="{{ route('admin.whistleblowing-queue') }}" class="balik">&larr; Semua Laporan</a>

@if (session('sukses'))
  <div class="kartu" style="border-color:var(--hijau);background:var(--hijau-muda)">{{ session('sukses') }}</div>
@endif
@if (session('gagal'))
  <div class="kartu" style="border-color:var(--merah);background:var(--merah-muda)">{{ session('gagal') }}</div>
@endif

<div class="kepala">
  <h2>{{ $categories[$report->category] ?? $report->category }}</h2>
  <span class="status {{ $report->status }}">{{ ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai'][$report->status] ?? $report->status }}</span>
</div>

<div class="kartu">
  <div class="baris"><div class="l">Pelapor</div><div>{{ $report->is_anonymous ? 'Anonim' : ($report->full_name ?? '—').' ('.($report->nrp ?? '—').')' }}</div></div>
  <div class="baris"><div class="l">Diajukan</div><div>{{ date('j F Y, H:i', strtotime($report->created_at)) }} WITA</div></div>
  @if ($report->reviewed_at)
    <div class="baris"><div class="l">Ditinjau</div><div>{{ date('j F Y, H:i', strtotime($report->reviewed_at)) }} WITA</div></div>
  @endif
  <div class="uraian">{{ $report->description }}</div>
</div>

@if ($report->resolution_notes)
  <div class="kartu">
    <div class="baris" style="border-bottom:0"><div class="l">Catatan Penyelesaian</div></div>
    <div class="uraian">{{ $report->resolution_notes }}</div>
  </div>
@endif

@if ($report->status === 'baru')
  <div class="kartu">
    <form method="POST" action="{{ route('admin.whistleblowing-start-processing', $report->id) }}">
      @csrf
      <button type="submit" class="utama">Mulai Tindak Lanjuti</button>
    </form>
  </div>
@elseif ($report->status === 'diproses')
  <div class="kartu">
    <form method="POST" action="{{ route('admin.whistleblowing-complete', $report->id) }}">
      @csrf
      <label style="display:block;font-size:11.5px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px">Catatan Penyelesaian</label>
      <textarea name="resolution_notes" placeholder="Ringkas hasil investigasi dan tindakan yang diambil..." required></textarea>
      <div><button type="submit" class="utama">Tandai Selesai</button></div>
    </form>
  </div>
@endif
@endsection
