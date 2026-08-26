@extends('layouts.app')

@section('judul', 'Ajukan Tukar Shift')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Tukar Shift</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Rekan yang dituju TIDAK perlu konfirmasi terpisah — pengajuan langsung ke Atasan Langsung
    untuk diputuskan. Kedua pihak wajib sudah punya penugasan shift pada tanggal yang diajukan.
  </div>

  <form method="POST" action="{{ route('shift.store') }}">
    @csrf
    <div class="bidang">
      <label for="counterpart_employee_id">Tukar Shift Dengan</label>
      <select id="counterpart_employee_id" name="counterpart_employee_id" required>
        <option value="">— Pilih rekan —</option>
        @foreach ($colleagues as $c)
          <option value="{{ $c->id }}" @selected(old('counterpart_employee_id') === $c->id)>{{ $c->full_name }} ({{ $c->nrp }})</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="swap_date">Tanggal</label>
      <input type="date" id="swap_date" name="swap_date" value="{{ old('swap_date') }}" required>
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
