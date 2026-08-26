@extends('layouts.app')

@section('judul', 'Impor Data Pegawai')
@section('peran', 'Admin Sistem')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.7}
.info ul{margin:6px 0 0 18px}
.aksi{margin-top:4px;display:flex;gap:8px}
@endsection

@section('isi')
<div class="kartu" style="max-width:640px">
  <div class="kartu-judul">Impor pegawai baru (CSV)</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Setiap baris CSV menghasilkan SATU usulan pegawai baru yang tetap menunggu persetujuan
    hr_approver satu per satu — sama seperti menambah pegawai satuan, impor ini TIDAK langsung
    membuat data pegawai.
    <ul>
      <li>Kolom wajib: <code>nrp, nama, tanggal_masuk, status_kepegawaian, kode_kantor, kode_jabatan</code></li>
      <li>Kolom opsional: <code>tanggal_lahir, jenis_kelamin (L/P), email, golongan, job_grade, status_kawin, tanggungan, tanggal_tetap, nrp_atasan, divisi, agama, nomor_ktp, nomor_npwp, bpjs_tenaga_kerja, bpjs_kesehatan, nomor_simpeda, nomor_tambora_rencana, tmt_pangkat, alamat, no_telepon, kontak_darurat_nama, kontak_darurat_hubungan, kontak_darurat_telepon, pendidikan_terakhir, pendidikan_jurusan</code></li>
      <li><code>status_kepegawaian</code>: tetap / trainee / kontrak / outsource — wajib isi <code>tanggal_tetap</code> bila "tetap"</li>
      <li><code>status_kawin</code>: "belum menikah" / "menikah"; <code>agama</code>: Islam/Kristen Protestan/Kristen Katolik/Hindu/Buddha/Konghucu; <code>pendidikan_terakhir</code>: SD/SMP/SMA/D3/S1/S2/S3</li>
      <li><code>kode_kantor</code>/<code>kode_jabatan</code> harus kode yang sudah ada di Daftar Kantor/Daftar Jabatan dan berstatus aktif; <code>nrp_atasan</code> (opsional) NRP pegawai yang sudah ada, kalau tidak dikenal akan dikosongkan tanpa membatalkan baris</li>
    </ul>
  </div>

  <a href="{{ route('sysadmin.employees.import.template') }}" class="btn luar" style="display:inline-block;margin-bottom:16px">Unduh contoh CSV</a>

  <form method="POST" action="{{ route('sysadmin.employees.import.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="bidang">
      <label for="berkas">Berkas CSV</label>
      <input type="file" id="berkas" name="berkas" accept=".csv,.txt" required>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Impor</button>
      <a href="{{ route('sysadmin.employees.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
