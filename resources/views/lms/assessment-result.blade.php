@extends('layouts.app')

@section('judul', 'Hasil Asesmen')
@section('peran', 'Employee Self Service')

@section('gaya')
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:24px;max-width:480px;text-align:center}
.skor{font-size:36px;font-weight:700;margin:12px 0}
.tag{display:inline-block;font-size:11px;font-weight:700;padding:4px 12px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.gagal{background:#FBE3E3;color:#9B2C2C}
@endsection

@section('isi')
<div class="kartu">
  <h2 style="font-size:16px;font-weight:700">{{ $attempt->assessment_title }}</h2>

  @if ($attempt->status === 'submitted')
    <p style="margin-top:12px;color:var(--teks-lemah);font-size:13px">Jawaban Anda terkirim — sebagian soal esai menunggu penilaian assessor.</p>
  @else
    <div class="skor">{{ $attempt->total_score }}</div>
    <span class="tag {{ $attempt->passed ? 'lulus' : 'gagal' }}">{{ $attempt->passed ? 'Lulus' : 'Tidak Lulus' }}</span>
    <p style="margin-top:12px;color:var(--teks-lemah);font-size:12px">Nilai lulus: {{ $attempt->passing_score }}</p>
  @endif
</div>
@endsection
