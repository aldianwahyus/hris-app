@extends('layouts.app')

@section('judul', 'Ajukan Dokumen')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Dokumen Mandiri</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Pilih jenis dokumen yang Anda butuhkan dan jelaskan keperluannya. Permintaan akan diproses
    oleh HC dan dapat diunduh sebagai PDF setelah diterbitkan.
  </div>

  <form method="POST" action="{{ route('documents.store') }}">
    @csrf
    <div class="bidang">
      <label for="document_type">Jenis Dokumen</label>
      <select id="document_type" name="document_type" required>
        @foreach ($documentTypes as $value => $label)
          <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="purpose">Keperluan</label>
      <textarea id="purpose" name="purpose" rows="3" maxlength="500" required>{{ old('purpose') }}</textarea>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('documents.history') }}" class="btn luar">Riwayat Pengajuan</a>
    </div>
  </form>
</div>
@endsection
