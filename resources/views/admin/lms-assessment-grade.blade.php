@extends('layouts.app')

@section('judul', 'Nilai Asesmen')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:12px}
.kartu h3{font-size:12.5px;font-weight:700;margin-bottom:6px}
.kartu p{font-size:12.5px;line-height:1.6;margin-bottom:8px;white-space:pre-wrap}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis);margin-bottom:8px}
.bidang{max-width:140px}
.bidang label{display:block;font-size:11px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px}
.bidang input{width:100%;padding:8px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.aksi{margin-top:8px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $attempt->assessment_title }} — {{ $attempt->full_name }}</h2>
  <p>{{ $attempt->nrp }} · Skor tersimpan otomatis untuk soal pilihan ganda, esai dinilai manual di bawah</p>
</div>

<form method="POST" action="{{ route('lms.admin.assessments.grade.store', $attempt->id) }}">
  @csrf
  @foreach ($answers as $ans)
    <div class="kartu">
      <span class="tag">{{ $ans->type === 'multiple_choice' ? 'Pilihan Ganda (otomatis)' : 'Esai' }}</span>
      <h3>{{ $ans->sequence }}. {{ $ans->question_text }}</h3>
      <p>{{ $ans->answer_text ?? '(tidak dijawab)' }}</p>
      @if ($ans->type === 'essay')
        <div class="bidang">
          <label>Nilai (maks {{ $ans->score_weight }})</label>
          <input type="number" name="skor[{{ $ans->question_id }}]" value="{{ $ans->score_awarded }}" min="0" max="{{ $ans->score_weight }}" step="0.01" required>
        </div>
      @else
        <div style="font-size:12px;color:var(--teks-lemah)">Nilai otomatis: {{ $ans->score_awarded }}</div>
      @endif
    </div>
  @endforeach

  <div class="aksi">
    <button type="submit" class="mini">Simpan Penilaian</button>
  </div>
</form>
@endsection
