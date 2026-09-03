@extends('layouts.public')

@section('judul', 'Tawaran Kerja')

@section('gaya')
.ringkasan-baris{display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--garis)}
.ringkasan-baris:last-child{border-bottom:0}
.status{display:inline-block;font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px}
.status.menunggu{background:var(--emas-muda);color:#7A5F0B}
.status.diterima{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditolak{background:var(--merah-muda);color:var(--merah)}
.aksi{display:flex;gap:10px;margin-top:20px}
@endsection

@section('isi')
<div class="kartu">
  <h1 style="font-size:18px;font-weight:800;margin-bottom:4px">Tawaran Kerja untuk {{ $offer->full_name }}</h1>
  <p style="font-size:12.5px;color:var(--teks-lemah);margin-bottom:14px">Ditawarkan pada {{ date('j M Y', strtotime($offer->offered_at)) }}</p>

  <span class="status {{ $offer->status }}">
    {{ ['menunggu' => 'Menunggu Respons Anda', 'diterima' => 'Sudah Diterima', 'ditolak' => 'Sudah Ditolak'][$offer->status] ?? $offer->status }}
  </span>

  <div style="margin-top:16px">
    <div class="ringkasan-baris"><span>Posisi</span><span>{{ $offer->position_name }}</span></div>
    <div class="ringkasan-baris"><span>Unit Kerja</span><span>{{ $offer->office_name }}</span></div>
    @if ($offer->proposed_salary_notes)
      <div class="ringkasan-baris"><span>Catatan Gaji</span><span>{{ $offer->proposed_salary_notes }}</span></div>
    @endif
  </div>

  @if ($offer->status === 'menunggu')
    <form method="POST" action="{{ route('careers.offer-respond', $token) }}" class="aksi">
      @csrf
      <button type="submit" name="keputusan" value="terima" class="btn">Terima Tawaran</button>
      <button type="submit" name="keputusan" value="tolak" class="btn luar" onclick="return confirm('Anda yakin ingin menolak tawaran ini?')">Tolak Tawaran</button>
    </form>
  @else
    <p style="font-size:12.5px;color:var(--teks-lemah);margin-top:16px">
      Anda sudah merespons tawaran ini @if ($offer->responded_at) pada {{ date('j M Y', strtotime($offer->responded_at)) }} @endif.
    </p>
  @endif
</div>
@endsection
