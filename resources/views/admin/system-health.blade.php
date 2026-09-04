@extends('layouts.app')

@section('judul', 'Kesehatan Sistem')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:11px;margin-bottom:20px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:16px}
.ring.gagal{border-color:#F3C6C2;background:var(--merah-muda)}
.ring .status{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;margin-bottom:6px}
.titik{width:9px;height:9px;border-radius:99px;flex-shrink:0}
.titik.ok{background:var(--hijau)}
.titik.gagal{background:var(--merah)}
.ring .l{font-size:11px;color:var(--teks-lemah);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:4px}
.ring .d{font-size:11.5px;color:var(--teks-lemah);line-height:1.5;word-break:break-word}
.log-kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.log-baris{font-family:'JetBrains Mono',monospace;font-size:10.5px;line-height:1.6;
  padding:8px 10px;background:var(--latar);border-radius:6px;margin-bottom:6px;
  word-break:break-word;color:#7A1F1F}
.kosong{padding:20px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Kesehatan Sistem</h2>
  <p>Status konektivitas komponen internal — diperbarui setiap kali halaman ini dimuat</p>
</div>

<div class="ringkas">
  @php
    $labelKomponen = ['database' => 'Basis Data', 'redis' => 'Redis', 'queue' => 'Antrean', 'storage' => 'Storage (S3/MinIO)'];
  @endphp
  @foreach ($checks as $key => $result)
    <div class="ring {{ $result['ok'] ? '' : 'gagal' }}">
      <div class="status">
        <span class="titik {{ $result['ok'] ? 'ok' : 'gagal' }}"></span>
        {{ $labelKomponen[$key] ?? $key }}
      </div>
      <div class="l">{{ $result['ok'] ? 'Sehat' : 'Bermasalah' }}</div>
      <div class="d">{{ $result['detail'] }}</div>
    </div>
  @endforeach
</div>

<div class="log-kartu">
  <div class="kartu-judul">Log Error Terakhir</div>
  @forelse ($recentErrors as $line)
    <div class="log-baris">{{ $line }}</div>
  @empty
    <div class="kosong">Tidak ada entri ERROR pada rentang log yang diperiksa.</div>
  @endforelse
</div>
@endsection
