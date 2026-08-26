@extends('layouts.app')

@section('judul', 'Riwayat Parameter')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.usai{background:var(--latar);color:var(--teks-lemah)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $parameter->code }} — {{ $parameter->name }}</h2>
    <p>Tipe: {{ $parameter->value_type }} · Modul: {{ $parameter->owner_module }} @if ($parameter->unit) · Satuan: {{ $parameter->unit }} @endif</p>
  </div>
  <a class="btn luar" href="{{ route('sysadmin.parameters.index') }}">← Kembali</a>
</div>

@if ($errors->any())
  <div class="pesan gagal">{{ $errors->first() }}</div>
@endif

<div class="kartu">
  <div class="kartu-judul">Tambah nilai baru</div>
  <form method="POST" action="{{ route('sysadmin.parameters.add-value', $parameter->id) }}"
    data-confirm="Nilai aktif saat ini akan ditutup (bukan dihapus) dan digantikan nilai baru mulai tanggal ini. Lanjutkan?">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label for="value">Nilai ({{ $parameter->value_type }})</label>
        <input type="text" id="value" name="value" value="{{ old('value') }}" required>
      </div>
      <div class="bidang">
        <label for="effective_from">Berlaku Sejak</label>
        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from') }}" required>
      </div>
    </div>
    <div class="bidang">
      <label for="source_document">Dasar (nomor SK/PMK, opsional)</label>
      <input type="text" id="source_document" name="source_document" value="{{ old('source_document') }}">
    </div>
    <button type="submit" class="btn">Simpan Nilai Baru</button>
  </form>
</div>

<div class="kartu">
  <div class="kartu-judul">Riwayat lengkap</div>
  <div class="gulir">
    <table>
      <thead>
        <tr><th>Nilai</th><th>Berlaku Sejak</th><th>Berlaku Sampai</th><th>Dasar</th><th>Status</th></tr>
      </thead>
      <tbody>
        @forelse ($values as $v)
          <tr>
            <td class="angka">{{ $v->value }}</td>
            <td class="angka">{{ date('j M Y', strtotime($v->effective_from)) }}</td>
            <td class="angka">{{ $v->effective_to ? date('j M Y', strtotime($v->effective_to)) : '—' }}</td>
            <td>{{ $v->source_document ?? '—' }}</td>
            <td><span class="status {{ $v->effective_to === null ? 'aktif' : 'usai' }}">{{ $v->effective_to === null ? 'Aktif' : 'Berakhir' }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center;color:var(--teks-lemah);padding:24px">Belum ada nilai.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
