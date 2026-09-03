@extends('layouts.app')

@section('judul', 'Ajukan Job Requisition')
@section('peran', 'Admin SDM')

@section('gaya')
.aksi{display:flex;gap:8px;margin-top:14px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Job Requisition</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('admin.recruitment-requisition-store') }}">
    @csrf
    <div class="bidang">
      <label for="office_id">Kantor</label>
      <select id="office_id" name="office_id" required>
        <option value="">— Pilih Kantor —</option>
        @foreach ($offices as $o)
          <option value="{{ $o->id }}" @selected(old('office_id') === $o->id)>{{ $o->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="position_id">Posisi</label>
      <select id="position_id" name="position_id" required>
        <option value="">— Pilih Posisi —</option>
        @foreach ($positions as $p)
          <option value="{{ $p->id }}" @selected(old('position_id') === $p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="requested_headcount">Jumlah Kebutuhan (Headcount)</label>
      <input type="number" id="requested_headcount" name="requested_headcount" min="1" max="50" value="{{ old('requested_headcount', 1) }}" required>
    </div>

    <div class="bidang">
      <label for="justification">Justifikasi</label>
      <textarea id="justification" name="justification" rows="3" maxlength="1000" required>{{ old('justification') }}</textarea>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Requisition</button>
      <a href="{{ route('admin.recruitment-requisition-index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
