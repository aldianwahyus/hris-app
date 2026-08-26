@extends('layouts.app')

@section('judul', 'Evaluasi Pelatihan')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;max-width:520px}
.bidang{margin-bottom:14px}
.bidang label{display:block;font-size:11px;font-weight:600;color:var(--teks-lemah);margin-bottom:6px}
.bidang textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.bintang{display:flex;gap:16px}
.bintang label{display:flex;flex-direction:column;align-items:center;gap:4px;font-size:16px;cursor:pointer}
.bintang span{font-size:10px;color:var(--teks-lemah)}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
@endsection

@section('isi')
<div class="kepala">
  <h2>Evaluasi pelatihan</h2>
  <p>{{ $enrollment->course_title }} — kepuasan Anda atas pelatihan ini (BRD §5.5 Level 1)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.evaluation.store', $enrollment->id) }}">
    @csrf
    <div class="bidang">
      <label>Skor Kepuasan</label>
      <div class="bintang">
        @foreach ([1, 2, 3, 4, 5] as $s)
          <label>
            <input type="radio" name="satisfaction_score" value="{{ $s }}" @checked(optional($existing)->satisfaction_score == $s) required>
            {{ $s }}
            <span>{{ $s === 1 ? 'Kurang' : ($s === 5 ? 'Sangat Baik' : '') }}</span>
          </label>
        @endforeach
      </div>
    </div>
    <div class="bidang">
      <label>Komentar (opsional)</label>
      <textarea name="satisfaction_comments" rows="4">{{ optional($existing)->satisfaction_comments }}</textarea>
    </div>
    <button type="submit" class="mini">Kirim Evaluasi</button>
  </form>
</div>
@endsection
