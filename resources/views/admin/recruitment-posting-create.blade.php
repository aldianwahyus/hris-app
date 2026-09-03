@extends('layouts.app')

@section('judul', 'Buka Lowongan')
@section('peran', 'Admin SDM')

@section('gaya')
.aksi{display:flex;gap:8px;margin-top:14px}
.kosong{padding:20px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kartu" style="max-width:600px">
  <div class="kartu-judul">Buka Lowongan Baru</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  @if ($requisitions->isEmpty())
    <div class="kosong">Tidak ada requisition yang sudah disetujui dan belum dibukakan lowongan.</div>
  @else
    <form method="POST" action="{{ route('admin.recruitment-posting-store') }}">
      @csrf
      <div class="bidang">
        <label for="requisition_id">Requisition</label>
        <select id="requisition_id" name="requisition_id" required>
          <option value="">— Pilih Requisition —</option>
          @foreach ($requisitions as $r)
            <option value="{{ $r->id }}" @selected(old('requisition_id') === $r->id)>{{ $r->position_name }} — {{ $r->office_name }} ({{ $r->requested_headcount }} orang)</option>
          @endforeach
        </select>
      </div>

      <div class="bidang">
        <label for="title">Judul Lowongan</label>
        <input type="text" id="title" name="title" maxlength="200" value="{{ old('title') }}" required>
      </div>

      <div class="bidang">
        <label for="employment_status_offered">Status Kepegawaian yang Ditawarkan</label>
        <select id="employment_status_offered" name="employment_status_offered" required>
          @foreach ($employmentStatuses as $value => $label)
            <option value="{{ $value }}" @selected(old('employment_status_offered') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="bidang">
        <label for="description">Deskripsi Pekerjaan</label>
        <textarea id="description" name="description" rows="4" maxlength="3000" required>{{ old('description') }}</textarea>
      </div>

      <div class="bidang">
        <label for="requirements">Persyaratan</label>
        <textarea id="requirements" name="requirements" rows="4" maxlength="3000" required>{{ old('requirements') }}</textarea>
      </div>

      <div class="aksi">
        <button type="submit" class="btn">Buka Lowongan</button>
        <a href="{{ route('admin.recruitment-posting-index') }}" class="btn luar">Batal</a>
      </div>
    </form>
  @endif
</div>
@endsection
