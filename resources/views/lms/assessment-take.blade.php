@extends('layouts.app')

@section('judul', 'Kerjakan Asesmen')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:12px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:10px}
.opsi label{display:flex;align-items:center;gap:8px;padding:8px 0;font-size:12.5px;cursor:pointer}
.kartu textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.aksi{margin-top:8px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $attempt->assessment_title }}</h2>
  <p>{{ $attempt->duration_minutes ? 'Durasi: '.$attempt->duration_minutes.' menit — ' : '' }}jawaban tersimpan saat Anda mengirim seluruh formulir</p>
</div>

<form method="POST" action="{{ route('lms.assessment.submit', $attempt->id) }}"
      data-confirm="Kirim jawaban? Anda tidak bisa mengubahnya setelah dikirim.">
  @csrf
  @foreach ($questions as $q)
    <div class="kartu">
      <h3>{{ $q->sequence }}. {{ $q->question_text }}</h3>
      @if ($q->type === 'multiple_choice')
        <div class="opsi">
          @foreach ($q->options as $label => $teks)
            @continue(trim((string) $teks) === '')
            <label>
              <input type="radio" name="jawaban[{{ $q->id }}]" value="{{ $label }}" required>
              {{ $label }}. {{ $teks }}
            </label>
          @endforeach
        </div>
      @else
        <textarea name="jawaban[{{ $q->id }}]" rows="4" required></textarea>
      @endif
    </div>
  @endforeach

  <div class="aksi">
    <button type="submit" class="mini">Kirim Jawaban</button>
  </div>
</form>
@endsection
