@extends('layouts.app')

@section('judul', 'Kelola Perpustakaan Digital')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil select,.bidang-kecil textarea{padding:8px 10px;
  border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.info{margin-bottom:12px;padding:9px 12px;background:var(--emas-muda);border:1px solid #E8D9A0;
  border-radius:8px;font-size:11px;color:#6B540A}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:9px 10px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:10px;border-bottom:1px solid var(--garis);font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody input{padding:5px 7px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:11.5px;width:100%}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
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
  <h2>Kelola perpustakaan digital</h2>
  <p>Repository materi pembelajaran — dokumen atau tautan eksternal</p>
</div>

<div class="kartu">
  <div class="info">Isi salah satu: unggah berkas ATAU isi tautan eksternal (video/referensi).</div>
  <form method="POST" action="{{ route('lms.admin.library.store') }}" enctype="multipart/form-data" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="flex:1;min-width:180px">
      <label>Judul</label>
      <input type="text" name="title" required maxlength="200">
    </div>
    <div class="bidang-kecil">
      <label>Kategori</label>
      <input type="text" name="category" maxlength="100" placeholder="mis. Kepatuhan">
    </div>
    <div class="bidang-kecil" style="min-width:160px">
      <label>Kursus Terkait (opsional)</label>
      <select name="course_id">
        <option value="">— Tidak terikat —</option>
        @foreach ($courses as $c)
          <option value="{{ $c->id }}">{{ $c->title }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Berkas</label>
      <input type="file" name="berkas" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
    </div>
    <div class="bidang-kecil" style="min-width:200px">
      <label>ATAU Tautan Eksternal</label>
      <input type="url" name="external_url" placeholder="https://...">
    </div>
    <div class="bidang-kecil" style="flex:2;min-width:220px">
      <label>Deskripsi</label>
      <input type="text" name="description">
    </div>
    <button type="submit" class="mini utama">Tambah Materi</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Kategori</th><th>Kursus</th><th>Tipe</th><th>Akses</th><th>Aktif</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($items as $item)
        @php $formId = 'materi-'.$item->id; @endphp
        <tr>
          <td><input form="{{ $formId }}" type="text" name="title" value="{{ $item->title }}" required maxlength="200"></td>
          <td><input form="{{ $formId }}" type="text" name="category" value="{{ $item->category }}" maxlength="100"></td>
          <td>{{ $item->course_title ?? '—' }}</td>
          <td>{{ $item->external_url ? 'Tautan' : 'Berkas' }}</td>
          <td class="angka">{{ $accessCounts[$item->id] ?? 0 }}</td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($item->is_active)>
          </td>
          <td>
            <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
            <form id="{{ $formId }}" method="POST" action="{{ route('lms.admin.library.update', $item->id) }}" style="display:none">
              @csrf
              <input type="hidden" name="description" value="{{ $item->description }}">
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="kosong">Belum ada materi.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
