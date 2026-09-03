@extends('layouts.public')

@section('judul', 'Lowongan Kerja')

@section('gaya')
.lowongan{display:block;color:inherit;text-decoration:none}
.lowongan-baris{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);
  padding:16px 18px;margin-bottom:10px;transition:.12s}
.lowongan-baris:hover{border-color:var(--hijau)}
.lowongan-judul{font-size:14.5px;font-weight:700}
.lowongan-info{font-size:12px;color:var(--teks-lemah);margin-top:4px}
.tag{display:inline-block;font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;
  background:var(--hijau-muda);color:var(--hijau-tua);margin-top:8px}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<h1 style="font-size:20px;font-weight:800;margin-bottom:6px">Bergabunglah dengan Kami</h1>
<p style="font-size:13px;color:var(--teks-lemah);margin-bottom:20px">Lowongan yang sedang dibuka di Bank NTB Syariah.</p>

@forelse ($postings as $p)
  <a href="{{ route('careers.show', $p->id) }}" class="lowongan">
    <div class="lowongan-baris">
      <div class="lowongan-judul">{{ $p->title }}</div>
      <div class="lowongan-info">{{ $p->office_name }}</div>
      <span class="tag">{{ ['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsource'][$p->employment_status_offered] ?? $p->employment_status_offered }}</span>
    </div>
  </a>
@empty
  <div class="kartu kosong">Belum ada lowongan yang dibuka saat ini. Silakan periksa kembali di lain waktu.</div>
@endforelse
@endsection
