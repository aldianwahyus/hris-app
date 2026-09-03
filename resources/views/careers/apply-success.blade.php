@extends('layouts.public')

@section('judul', 'Lamaran Terkirim')

@section('isi')
<div class="kartu" style="text-align:center;padding:36px 20px">
  <div style="font-size:36px;margin-bottom:12px">✅</div>
  <h1 style="font-size:18px;font-weight:800;margin-bottom:8px">Lamaran Anda Telah Terkirim</h1>
  <p style="font-size:13px;color:var(--teks-lemah);margin-bottom:20px">
    Terima kasih atas minat Anda bergabung dengan Bank NTB Syariah. Tim rekrutmen kami akan
    meninjau lamaran Anda dan menghubungi Anda melalui email bila lolos ke tahap berikutnya.
  </p>
  <a href="{{ route('careers.index') }}" class="btn">Lihat Lowongan Lainnya</a>
</div>
@endsection
