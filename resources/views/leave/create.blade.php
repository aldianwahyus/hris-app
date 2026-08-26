@extends('layouts.app')

@section('judul', 'Ajukan Cuti')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Cuti Tahunan</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Kantong <strong>jatah tahun berjalan</strong> dipakai lebih dulu, baru sisa tahun lalu.
    Pengambilan cuti tahunan <strong>pertama</strong> pada tahun ini wajib sekaligus minimal
    <strong>5 hari</strong> — pengambilan berikutnya tidak lagi terikat batas ini.
  </div>

  <form method="POST" action="{{ route('leave.store') }}">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label for="start_date">Tanggal Mulai</label>
        <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
      </div>
      <div class="bidang">
        <label for="end_date">Tanggal Selesai</label>
        <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
      </div>
    </div>

    <div class="bidang">
      <label for="reason">Alasan (opsional)</label>
      <textarea id="reason" name="reason" rows="3">{{ old('reason') }}</textarea>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('ess.dashboard') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
