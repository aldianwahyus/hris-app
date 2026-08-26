@extends('layouts.app')

@section('judul', 'Kompetensi')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input{padding:8px 10px;border:1px solid var(--garis);border-radius:7px;
  font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:9px 10px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:8px 10px;border-bottom:1px solid var(--garis);font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody input{padding:5px 7px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:11.5px;width:100%}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.centang{display:flex;align-items:center;gap:4px;justify-content:center}
@endsection

@section('isi')
<div class="kepala">
  <h2>Kompetensi</h2>
  <p>Katalog kompetensi organisasi — dipetakan ke jabatan (level wajib) dan kursus (kompetensi yang dikembangkan)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.competencies.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode</label>
      <input type="text" name="code" required maxlength="30" placeholder="mis. COMP-01" style="width:110px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:180px">
      <label>Nama</label>
      <input type="text" name="name" required maxlength="150" placeholder="mis. Layanan Pelanggan">
    </div>
    <div class="bidang-kecil">
      <label>Kategori</label>
      <input type="text" name="category" maxlength="100" placeholder="mis. Leadership">
    </div>
    <div class="bidang-kecil" style="flex:2;min-width:220px">
      <label>Deskripsi</label>
      <input type="text" name="description">
    </div>
    <button type="submit" class="mini utama">Tambah Kompetensi</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Aktif</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($competencies as $c)
        @php $formId = 'komp-'.$c->id; @endphp
        <tr>
          <td class="angka">{{ $c->code }}</td>
          <td><input form="{{ $formId }}" type="text" name="name" value="{{ $c->name }}" required maxlength="150"></td>
          <td><input form="{{ $formId }}" type="text" name="category" value="{{ $c->category }}" maxlength="100"></td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($c->is_active)>
          </td>
          <td>
            <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
            <form id="{{ $formId }}" method="POST" action="{{ route('lms.admin.competencies.update', $c->id) }}" style="display:none">
              @csrf
              <input type="hidden" name="description" value="{{ $c->description }}">
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada kompetensi.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
