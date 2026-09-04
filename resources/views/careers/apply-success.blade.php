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
  <div style="background:var(--latar);border-radius:8px;padding:14px 16px;margin-bottom:20px;text-align:left">
    <p style="font-size:12px;font-weight:700;margin-bottom:6px">Simpan tautan ini untuk memeriksa status lamaran Anda:</p>
    <a href="{{ route('careers.status', $statusToken) }}" style="font-size:12px;word-break:break-all">{{ route('careers.status', $statusToken) }}</a>
    <p style="font-size:11px;color:var(--teks-lemah);margin-top:8px">
      Tautan ini TIDAK dikirim ulang lewat email — simpan atau catat sekarang.
    </p>
  </div>
  <a href="{{ route('careers.index') }}" class="btn">Lihat Lowongan Lainnya</a>
</div>
@endsection
