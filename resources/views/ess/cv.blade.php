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
.foto-baris{display:flex;align-items:center;gap:16px}
.foto-bulat{width:84px;height:84px;border-radius:50%;object-fit:cover;
  background:var(--latar);border:1px solid var(--garis)}
.foto-placeholder{width:84px;height:84px;border-radius:50%;background:var(--hijau-muda);
  color:var(--hijau-tua);display:flex;align-items:center;justify-content:center;
  font-size:26px;font-weight:800;border:1px solid var(--garis)}
.foto-aksi{display:flex;flex-direction:column;gap:8px}
.foto-aksi .baris{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.foto-ket{font-size:11px;color:var(--teks-lemah)}
.foto-hapus{background:none;border:none;color:var(--merah);font-size:12px;font-weight:700;
  cursor:pointer;padding:0;font-family:inherit}
@endsection

@section('isi')
<div class="kepala">
  <h2>CV Saya</h2>
  <p>Data organisasi (jabatan/kantor/grade) hanya-baca — perubahannya lewat Admin SDM. Data pribadi di bawah bisa Anda ubah sendiri, langsung tersimpan.</p>
  <a href="{{ route('ess.cv.pdf') }}" class="btn luar" style="display:inline-block;margin-top:10px;text-decoration:none">Unduh PDF</a>
</div>

@if ($errors->hasBag('photo'))
  <div class="pesan gagal">{{ $errors->getBag('photo')->first() }}</div>
@endif

<div class="kartu">
  <div class="kartu-judul">Foto Profil</div>
  <div class="foto-baris">
    @if ($employee->photo_path)
      <img src="{{ route('ess.cv.photo') }}" alt="Foto {{ $employee->full_name }}" class="foto-bulat">
    @else
      <div class="foto-placeholder">{{ strtoupper(substr($employee->full_name, 0, 1)) }}</div>
    @endif

    <div class="foto-aksi">
      <form method="POST" action="{{ route('ess.cv.photo.update') }}" enctype="multipart/form-data" class="baris">
        @csrf
        <input type="file" name="photo" accept=".jpg,.jpeg,.png" required>
        <button type="submit" class="btn" style="padding:8px 14px">Unggah</button>
      </form>
      <span class="foto-ket">JPG atau PNG, maks 2 MB.</span>
      @if ($employee->photo_path)
        <form method="POST" action="{{ route('ess.cv.photo.destroy') }}" data-confirm="Hapus foto profil?">
          @csrf
          @method('DELETE')
          <button type="submit" class="foto-hapus">Hapus foto</button>
        </form>
      @endif
    </div>
  </div>
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
