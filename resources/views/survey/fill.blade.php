@extends('layouts.app')

@section('judul', $survey->title)
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.pertanyaan{margin-bottom:20px}
.pertanyaan-teks{font-weight:600;font-size:13px;margin-bottom:8px}
.skala{display:flex;gap:6px;flex-wrap:wrap}
.skala label{display:flex;flex-direction:column;align-items:center;gap:4px;font-size:11px;
  color:var(--teks-lemah);cursor:pointer}
.skala input{cursor:pointer}
.pilihan label{display:flex;align-items:center;gap:8px;font-size:12.5px;padding:6px 0;cursor:pointer}
.aksi{display:flex;gap:8px;margin-top:8px}
@endsection

@section('isi')
<div class="kartu" style="max-width:620px">
  <div class="kartu-judul">{{ $survey->title }}</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  @if ($survey->description)
    <div class="info">{{ $survey->description }}</div>
  @endif

  @if ($survey->is_anonymous)
    <div class="info">Survei ini bersifat ANONIM — jawaban Anda tidak akan dikaitkan dengan identitas Anda.</div>
  @endif

  <form method="POST" action="{{ route('survey.submit', $survey->id) }}">
    @csrf
    @foreach ($questions as $q)
      <div class="pertanyaan">
        <div class="pertanyaan-teks">{{ $loop->iteration }}. {{ $q->question_text }}</div>

        @if ($q->question_type === 'nps_0_10')
          <div class="skala">
            @for ($i = 0; $i <= 10; $i++)
              <label>
                <input type="radio" name="jawaban[{{ $q->id }}]" value="{{ $i }}" required>
                {{ $i }}
              </label>
            @endfor
          </div>
        @elseif ($q->question_type === 'rating_1_5')
          <div class="skala">
            @for ($i = 1; $i <= 5; $i++)
              <label>
                <input type="radio" name="jawaban[{{ $q->id }}]" value="{{ $i }}" required>
                {{ $i }}
              </label>
            @endfor
          </div>
        @elseif ($q->question_type === 'pilihan_ganda')
          <div class="pilihan">
            @foreach ($q->options as $opsi)
              <label>
                <input type="radio" name="jawaban[{{ $q->id }}]" value="{{ $opsi }}" required>
                {{ $opsi }}
              </label>
            @endforeach
          </div>
        @else
          <textarea name="jawaban[{{ $q->id }}]" rows="3" required></textarea>
        @endif
      </div>
    @endforeach

    <div class="aksi">
      <button type="submit" class="btn">Kirim Jawaban</button>
      <a href="{{ route('survey.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
