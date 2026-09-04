@extends('layouts.public')

@section('judul', 'Status Lamaran')

@section('gaya')
.ringkasan-baris{display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--garis)}
.ringkasan-baris:last-child{border-bottom:0}
.status{display:inline-block;font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px}
.status.melamar{background:var(--emas-muda);color:#7A5F0B}
.status.seleksi_berkas{background:#DCEAFB;color:#1D4E89}
.status.wawancara{background:#EAE2F8;color:#5B2A9E}
.status.penawaran{background:var(--emas-muda);color:#7A5F0B}
.status.diterima{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditolak{background:var(--merah-muda);color:var(--merah)}
@endsection

@section('isi')
<div class="kartu">
  <h1 style="font-size:18px;font-weight:800;margin-bottom:4px">Status Lamaran {{ $application->full_name }}</h1>
  <p style="font-size:12.5px;color:var(--teks-lemah);margin-bottom:14px">
    Melamar posisi {{ $application->posting_title }} pada {{ date('j M Y', strtotime($application->applied_at)) }}
  </p>

  <span class="status {{ $application->status }}">
    {{ ['melamar' => 'Melamar', 'seleksi_berkas' => 'Seleksi Berkas', 'wawancara' => 'Wawancara', 'penawaran' => 'Penawaran', 'diterima' => 'Diterima', 'ditolak' => 'Tidak Lolos'][$application->status] ?? $application->status }}
  </span>

  <p style="font-size:12px;color:var(--teks-lemah);margin-top:16px">
    Tim rekrutmen kami akan menghubungi Anda melalui email untuk setiap perkembangan tahap seleksi.
  </p>
</div>
@endsection
