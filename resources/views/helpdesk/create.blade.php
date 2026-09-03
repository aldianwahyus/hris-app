@extends('layouts.app')

@section('judul', 'Ajukan Tiket Bantuan')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Tiket Bantuan</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Sampaikan pertanyaan atau kendala administratif Anda ke HC. Tim HC akan membalas
    langsung pada tiket ini.
  </div>

  <form method="POST" action="{{ route('helpdesk.store') }}">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label for="category">Kategori</label>
        <select id="category" name="category" required>
          @foreach ($categories as $value => $label)
            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="priority">Prioritas</label>
        <select id="priority" name="priority" required>
          @foreach ($priorities as $value => $label)
            <option value="{{ $value }}" @selected(old('priority', 'sedang') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="bidang">
      <label for="subject">Judul</label>
      <input type="text" id="subject" name="subject" maxlength="150" value="{{ old('subject') }}" required>
    </div>

    <div class="bidang">
      <label for="description">Detail</label>
      <textarea id="description" name="description" rows="4" maxlength="2000" required>{{ old('description') }}</textarea>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Tiket</button>
      <a href="{{ route('helpdesk.index') }}" class="btn luar">Tiket Saya</a>
    </div>
  </form>
</div>
@endsection
