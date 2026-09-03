@extends('layouts.app')

@section('judul', 'Impor Data Kantor')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.7}
.info ul{margin:6px 0 0 18px}
.aksi{margin-top:4px;display:flex;gap:8px}
@endsection

@section('isi')
<div class="kartu" style="max-width:640px">
  <div class="kartu-judul">Impor kantor (CSV)</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Setiap baris CSV yang lolos LANGSUNG menjadi kantor aktif — beda dari impor pegawai, kantor
    tidak punya antrean persetujuan.
    <ul>
      <li>Kolom wajib: <code>kode, nama, tipe, zona_waktu</code></li>
      <li>Kolom opsional: <code>alamat, kelas, kode_kantor_induk</code></li>
      <li><code>tipe</code>: head_office / branch / sub_branch / functional</li>
      <li><code>zona_waktu</code>: Asia/Makassar (WITA) / Asia/Jakarta (WIB) / Asia/Jayapura (WIT)</li>
      <li><code>kode</code> wajib unik (belum dipakai kantor lain); <code>kode_kantor_induk</code> (opsional) harus kode kantor yang sudah ada dan aktif — boleh merujuk kantor yang BARU dibuat di baris sebelumnya pada berkas yang sama (taruh induk lebih dulu, baru cabangnya)</li>
    </ul>
  </div>

  <a href="{{ route('sysadmin.offices.import.template') }}" class="btn luar" style="display:inline-block;margin-bottom:16px">Unduh contoh CSV</a>

  <form method="POST" action="{{ route('sysadmin.offices.import.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="bidang">
      <label for="berkas">Berkas CSV</label>
      <input type="file" id="berkas" name="berkas" accept=".csv,.txt" required>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Impor</button>
      <a href="{{ route('sysadmin.offices.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
