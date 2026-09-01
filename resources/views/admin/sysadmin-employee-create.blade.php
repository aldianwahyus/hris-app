@extends('layouts.app')

@section('judul', 'Tambah Pegawai Baru')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.bagian{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
  color:var(--teks-lemah);margin:22px 0 10px;padding-top:14px;border-top:1px solid var(--garis)}
.bagian:first-of-type{margin-top:4px;padding-top:0;border-top:0}
@endsection

@section('isi')
<div class="kepala">
  <h2>Tambah pegawai baru</h2>
  <p>Pengajuan ini menunggu persetujuan hr_approver — baris data pegawai & akun login baru BARU dibuat setelah disetujui.</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.employees.store') }}" enctype="multipart/form-data">
    @csrf

    <div class="bidang">
      <label for="photo">Foto Pegawai (opsional — JPG/PNG, maks 2 MB)</label>
      <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png">
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="nrp">NRP</label>
        <input type="text" name="nrp" id="nrp" value="{{ old('nrp') }}" required>
      </div>
      <div class="bidang">
        <label for="full_name">Nama Lengkap</label>
        <input type="text" name="full_name" id="full_name" value="{{ old('full_name') }}" required>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="birth_date">Tanggal Lahir</label>
        <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date') }}">
      </div>
      <div class="bidang">
        <label for="gender">Jenis Kelamin</label>
        <select name="gender" id="gender">
          <option value="">— Pilih —</option>
          <option value="L" @selected(old('gender') === 'L')>Laki-laki</option>
          <option value="P" @selected(old('gender') === 'P')>Perempuan</option>
        </select>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}">
        <div class="ket">Kosongkan untuk pakai email contoh otomatis.</div>
      </div>
      <div class="bidang">
        <label for="join_date">Tanggal Bergabung</label>
        <input type="date" name="join_date" id="join_date" value="{{ old('join_date') }}" required>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="office_id">Kantor</label>
        <select name="office_id" id="office_id" required>
          <option value="">— Pilih —</option>
          @foreach ($offices as $o)
            <option value="{{ $o->id }}" @selected(old('office_id') === $o->id)>{{ $o->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="position_id">Jabatan</label>
        <select name="position_id" id="position_id" required>
          <option value="">— Pilih —</option>
          @foreach ($positions as $p)
            <option value="{{ $p->id }}" @selected(old('position_id') === $p->id)>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="employment_status">Status Kepegawaian</label>
        <select name="employment_status" id="employment_status" required>
          @foreach (['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsource'] as $val => $label)
            <option value="{{ $val }}" @selected(old('employment_status') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="person_grade">Person Grade</label>
        <input type="number" name="person_grade" id="person_grade" min="1" max="255" value="{{ old('person_grade') }}">
      </div>
      <div class="bidang">
        <label for="job_grade">Job Grade</label>
        <input type="number" name="job_grade" id="job_grade" min="1" max="255" value="{{ old('job_grade') }}">
        <div class="ket">Tunjangan/salary step bisa dilengkapi lewat "Ubah" setelah pegawai dibuat.</div>
      </div>
    </div>

    <div class="bagian">Data Kepegawaian Tambahan</div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="permanent_date">Tanggal Jadi Pegawai Tetap</label>
        <input type="date" name="permanent_date" id="permanent_date" value="{{ old('permanent_date') }}">
        <div class="ket">Wajib diisi bila Status Kepegawaian di atas "Tetap".</div>
      </div>
      <div class="bidang">
        <label for="tmt_pangkat">TMT Pangkat</label>
        <input type="date" name="tmt_pangkat" id="tmt_pangkat" value="{{ old('tmt_pangkat') }}">
        <div class="ket">Tanggal mulai berlaku pangkat/jabatan.</div>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="marital_status">Status Kawin (PTKP)</label>
        <select name="marital_status" id="marital_status">
          <option value="">— Pilih —</option>
          <option value="belum menikah" @selected(old('marital_status') === 'belum menikah')>Belum Menikah</option>
          <option value="menikah" @selected(old('marital_status') === 'menikah')>Menikah</option>
        </select>
      </div>
      <div class="bidang">
        <label for="tanggungan">Jumlah Tanggungan (PTKP, maks. 3)</label>
        <input type="number" name="tanggungan" id="tanggungan" min="0" max="3" value="{{ old('tanggungan') }}">
        <div class="ket">Menentukan golongan tarif TER PPh 21 (PMK 168/2023).</div>
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="supervisor_id">Atasan Langsung (untuk Struktur Organisasi)</label>
        <select name="supervisor_id" id="supervisor_id">
          <option value="">— Tidak ada (puncak bagan) —</option>
          @foreach ($employeesForSupervisor as $e)
            <option value="{{ $e->id }}" @selected(old('supervisor_id') === $e->id)>{{ $e->full_name }} ({{ $e->nrp }})</option>
          @endforeach
        </select>
        <div class="ket">Murni untuk tampilan bagan — TIDAK memengaruhi wewenang persetujuan.</div>
      </div>
      <div class="bidang">
        <label for="division">Divisi</label>
        <input type="text" name="division" id="division" maxlength="100" value="{{ old('division') }}" placeholder="mis. Divisi Operasional — relevan untuk Kantor Pusat">
      </div>
    </div>

    <div class="bagian">Identitas &amp; BPJS</div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="agama">Agama</label>
        <select name="agama" id="agama">
          <option value="">— Pilih —</option>
          @foreach (['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $opt)
            <option value="{{ $opt }}" @selected(old('agama') === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="nomor_ktp">Nomor KTP</label>
        <input type="text" name="nomor_ktp" id="nomor_ktp" maxlength="20" value="{{ old('nomor_ktp') }}">
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="nomor_npwp">Nomor NPWP</label>
        <input type="text" name="nomor_npwp" id="nomor_npwp" maxlength="25" value="{{ old('nomor_npwp') }}">
      </div>
      <div class="bidang">
        <label for="bpjs_tenaga_kerja">BPJS Ketenagakerjaan</label>
        <input type="text" name="bpjs_tenaga_kerja" id="bpjs_tenaga_kerja" maxlength="30" value="{{ old('bpjs_tenaga_kerja') }}">
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="bpjs_kesehatan">BPJS Kesehatan</label>
        <input type="text" name="bpjs_kesehatan" id="bpjs_kesehatan" maxlength="30" value="{{ old('bpjs_kesehatan') }}">
      </div>
      <div class="bidang">
        <label for="nomor_simpeda">Nomor Rekening Simpeda</label>
        <input type="text" name="nomor_simpeda" id="nomor_simpeda" maxlength="30" value="{{ old('nomor_simpeda') }}">
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="nomor_tambora_rencana">Nomor Rekening Tambora Rencana</label>
        <input type="text" name="nomor_tambora_rencana" id="nomor_tambora_rencana" maxlength="30" value="{{ old('nomor_tambora_rencana') }}">
      </div>
    </div>

    <div class="bagian">Data Pribadi</div>

    <div class="bidang">
      <label for="alamat">Alamat</label>
      <textarea name="alamat" id="alamat" rows="3">{{ old('alamat') }}</textarea>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="no_telepon">No. Telepon</label>
        <input type="text" name="no_telepon" id="no_telepon" maxlength="20" value="{{ old('no_telepon') }}">
      </div>
      <div class="bidang">
        <label for="pendidikan_terakhir">Pendidikan Terakhir</label>
        <select name="pendidikan_terakhir" id="pendidikan_terakhir">
          <option value="">— Pilih —</option>
          @foreach (['SD', 'SMP', 'SMA', 'D3', 'S1', 'S2', 'S3'] as $jenjang)
            <option value="{{ $jenjang }}" @selected(old('pendidikan_terakhir') === $jenjang)>{{ $jenjang }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="bidang">
      <label for="pendidikan_jurusan">Jurusan</label>
      <input type="text" name="pendidikan_jurusan" id="pendidikan_jurusan" maxlength="100" value="{{ old('pendidikan_jurusan') }}">
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="kontak_darurat_nama">Kontak Darurat — Nama</label>
        <input type="text" name="kontak_darurat_nama" id="kontak_darurat_nama" maxlength="150" value="{{ old('kontak_darurat_nama') }}">
      </div>
      <div class="bidang">
        <label for="kontak_darurat_hubungan">Kontak Darurat — Hubungan</label>
        <input type="text" name="kontak_darurat_hubungan" id="kontak_darurat_hubungan" maxlength="50" value="{{ old('kontak_darurat_hubungan') }}" placeholder="mis. Suami/Istri, Orang Tua">
      </div>
    </div>

    <div class="bidang">
      <label for="kontak_darurat_telepon">Kontak Darurat — No. Telepon</label>
      <input type="text" name="kontak_darurat_telepon" id="kontak_darurat_telepon" maxlength="20" value="{{ old('kontak_darurat_telepon') }}">
    </div>

    <div class="aksi" style="display:flex;gap:8px;margin-top:20px">
      <button type="submit" class="btn">Ajukan Pegawai Baru</button>
      <a href="{{ route('sysadmin.employees.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
