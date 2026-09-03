@extends('layouts.app')

@section('judul', 'Ajukan Pemisahan Pegawai')
@section('peran', 'Admin SDM')

@section('gaya')
.aksi{display:flex;gap:8px;margin-top:14px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Pemisahan Pegawai</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('admin.offboarding-store') }}">
    @csrf
    <div class="bidang">
      <label for="employee_id">Pegawai</label>
      <select id="employee_id" name="employee_id" required>
        <option value="">— Pilih Pegawai —</option>
        @foreach ($employees as $e)
          <option value="{{ $e->id }}" @selected(old('employee_id') === $e->id)>{{ $e->full_name }} ({{ $e->nrp }})</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="separation_type">Jenis Pemisahan</label>
      <select id="separation_type" name="separation_type" required>
        @foreach ($separationTypes as $value => $label)
          <option value="{{ $value }}" @selected(old('separation_type') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="requested_last_date">Tanggal Terakhir Bekerja</label>
      <input type="date" id="requested_last_date" name="requested_last_date" value="{{ old('requested_last_date') }}" required>
    </div>

    <div class="bidang">
      <label for="reason">Alasan / Keterangan</label>
      <textarea id="reason" name="reason" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('admin.offboarding-index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
