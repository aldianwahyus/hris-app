@extends('layouts.public')

@section('judul', $posting->title)

@section('gaya')
.tag{display:inline-block;font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;
  background:var(--hijau-muda);color:var(--hijau-tua);margin-bottom:12px}
.blok{font-size:13px;line-height:1.7;white-space:pre-line;margin-bottom:16px}
.blok h3{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
  color:var(--teks-lemah);margin-bottom:6px}
@endsection

@section('isi')
<a href="{{ route('careers.index') }}" class="btn luar" style="margin-bottom:16px;padding:6px 12px;font-size:12px">← Semua Lowongan</a>

<div class="kartu">
  <h1 style="font-size:19px;font-weight:800;margin-bottom:4px">{{ $posting->title }}</h1>
  <p style="font-size:12.5px;color:var(--teks-lemah);margin-bottom:10px">{{ $posting->office_name }}</p>
  <span class="tag">{{ ['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsource'][$posting->employment_status_offered] ?? $posting->employment_status_offered }}</span>

  <div class="blok">
    <h3>Deskripsi</h3>
    {{ $posting->description }}
  </div>
  <div class="blok">
    <h3>Persyaratan</h3>
    {{ $posting->requirements }}
  </div>
</div>

<div class="kartu">
  <div class="kartu-judul">Lamar Posisi Ini</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('careers.apply', $posting->id) }}" enctype="multipart/form-data">
    @csrf
    <div class="bidang">
      <label for="full_name">Nama Lengkap</label>
      <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
    </div>
    <div class="bidang">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="{{ old('email') }}" required>
    </div>
    <div class="bidang">
      <label for="phone">Nomor Telepon</label>
      <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
    </div>
    <div class="bidang">
      <label for="resume">CV (PDF/DOC/DOCX, maks 5 MB)</label>
      <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
    </div>
    <button type="submit" class="btn">Kirim Lamaran</button>
  </form>
</div>
@endsection
