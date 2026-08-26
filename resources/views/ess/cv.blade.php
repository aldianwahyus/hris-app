@extends('layouts.app')

@section('judul', 'CV Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.baca{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baca:last-child{border-bottom:0}
.baca .l{color:var(--teks-lemah)}
@endsection

@section('isi')
<div class="kepala">
  <h2>CV Saya</h2>
  <p>Data organisasi (jabatan/kantor/grade) hanya-baca — perubahannya lewat Admin SDM. Data pribadi di bawah bisa Anda ubah sendiri, langsung tersimpan.</p>
  <a href="{{ route('ess.cv.pdf') }}" class="btn luar" style="display:inline-block;margin-top:10px;text-decoration:none">Unduh PDF</a>
</div>

<div class="kartu">
  <div class="kartu-judul">Data Organisasi (hanya-baca)</div>
  <div class="baca"><span class="l">NRP</span><span class="angka">{{ $employee->nrp }}</span></div>
  <div class="baca"><span class="l">Nama</span><span>{{ $employee->full_name }}</span></div>
  <div class="baca"><span class="l">Kantor</span><span>{{ $employee->office_name }}</span></div>
  <div class="baca"><span class="l">Jabatan</span><span>{{ $employee->position_name }}</span></div>
  <div class="baca"><span class="l">Status Kepegawaian</span><span>{{ ucfirst($employee->employment_status) }}</span></div>
  <div class="baca"><span class="l">Tanggal Bergabung</span><span class="angka">{{ $employee->join_date }}</span></div>
</div>

<div class="kartu">
  <div class="kartu-judul">Data Pribadi (bisa diubah sendiri)</div>

  <form method="POST" action="{{ route('ess.cv.update') }}">
    @csrf

    <div class="bidang">
      <label for="alamat">Alamat</label>
      <textarea name="alamat" id="alamat" rows="3">{{ old('alamat', $employee->alamat) }}</textarea>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="no_telepon">No. Telepon</label>
        <input type="text" name="no_telepon" id="no_telepon" value="{{ old('no_telepon', $employee->no_telepon) }}">
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="kontak_darurat_nama">Kontak Darurat — Nama</label>
        <input type="text" name="kontak_darurat_nama" id="kontak_darurat_nama" value="{{ old('kontak_darurat_nama', $employee->kontak_darurat_nama) }}">
      </div>
      <div class="bidang">
        <label for="kontak_darurat_hubungan">Kontak Darurat — Hubungan</label>
        <input type="text" name="kontak_darurat_hubungan" id="kontak_darurat_hubungan" value="{{ old('kontak_darurat_hubungan', $employee->kontak_darurat_hubungan) }}" placeholder="mis. Suami/Istri, Orang Tua">
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="kontak_darurat_telepon">Kontak Darurat — No. Telepon</label>
        <input type="text" name="kontak_darurat_telepon" id="kontak_darurat_telepon" value="{{ old('kontak_darurat_telepon', $employee->kontak_darurat_telepon) }}">
      </div>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="pendidikan_terakhir">Pendidikan Terakhir</label>
        <select name="pendidikan_terakhir" id="pendidikan_terakhir">
          <option value="">— Pilih —</option>
          @foreach (['SD', 'SMP', 'SMA', 'D3', 'S1', 'S2', 'S3'] as $jenjang)
            <option value="{{ $jenjang }}" @selected(old('pendidikan_terakhir', $employee->pendidikan_terakhir) === $jenjang)>{{ $jenjang }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="pendidikan_jurusan">Jurusan</label>
        <input type="text" name="pendidikan_jurusan" id="pendidikan_jurusan" value="{{ old('pendidikan_jurusan', $employee->pendidikan_jurusan) }}">
      </div>
    </div>

    <button type="submit" class="btn">Simpan Perubahan</button>
  </form>
</div>

@include('ess._decision-letters', ['decisionLetters' => $decisionLetters])
@include('ess._trainings', ['trainings' => $trainings])
@include('ess._certifications', ['certifications' => $certifications])
@include('ess._organizations', ['organizations' => $organizations])
@include('ess._awards', ['awards' => $awards])
@endsection
