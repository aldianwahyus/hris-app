@extends('layouts.app')

@section('judul', 'Buat Diskusi')
@section('peran', 'Employee Self Service')

@section('gaya')
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;max-width:560px}
.bidang{margin-bottom:12px}
.bidang label{display:block;font-size:11px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px}
.bidang input,.bidang select,.bidang textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
@endsection

@section('isi')
<div class="kartu">
  <form method="POST" action="{{ route('lms.forum.store') }}">
    @csrf
    <div class="bidang">
      <label>Kursus Terkait (opsional)</label>
      <select name="course_id">
        <option value="">— Umum —</option>
        @foreach ($courses as $c)
          <option value="{{ $c->id }}">{{ $c->title }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang">
      <label>Judul</label>
      <input type="text" name="title" required maxlength="200">
    </div>
    <div class="bidang">
      <label>Isi</label>
      <textarea name="body" required rows="6"></textarea>
    </div>
    <button type="submit" class="mini">Buat Diskusi</button>
  </form>
</div>
@endsection
