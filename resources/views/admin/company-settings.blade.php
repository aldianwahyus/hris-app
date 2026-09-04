@extends('layouts.app')

@section('judul', 'Pengaturan Perusahaan')
@section('peran', 'Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px;max-width:640px;line-height:1.6}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:20px;max-width:520px}
.grup-input{margin-bottom:18px}
label.field{display:block;font-size:11.5px;font-weight:600;color:var(--teks-lemah);margin-bottom:6px}
input[type="text"]{width:100%;border:1px solid var(--garis);border-radius:7px;padding:9px 11px;
  font-family:inherit;font-size:13px}
.pratinjau{display:flex;align-items:center;gap:14px;margin-bottom:12px}
.pratinjau img{height:52px;max-width:180px;object-fit:contain;border:1px solid var(--garis);border-radius:8px;padding:8px;background:#fff}
.ket-file{font-size:11px;color:var(--teks-lemah)}
input[type="file"]{font-size:12.5px}
.utama{padding:9px 18px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff;margin-top:6px}
.utama:hover{background:var(--hijau-tua)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pengaturan Perusahaan</h2>
  <p>
    Nama dan lambang perusahaan di sini SECARA OTOMATIS tampil pada seluruh dokumen resmi cetak
    (Memo Internal, Nota Debet, Jurnal Slip, Surat Keterangan, dan dokumen lain yang memakai kop
    surat perusahaan) — mengubahnya di sini TIDAK memerlukan perubahan kode apa pun.
  </p>
</div>

@if (session('sukses'))
  <div class="kartu" style="border-color:var(--hijau);background:var(--hijau-muda);max-width:520px;margin-bottom:16px">{{ session('sukses') }}</div>
@endif

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.company-settings.update') }}" enctype="multipart/form-data">
    @csrf

    <div class="grup-input">
      <label class="field">Lambang Perusahaan Saat Ini</label>
      <div class="pratinjau">
        <img src="{{ \App\Interfaces\Http\Support\CompanyProfile::logoDataUri() }}" alt="Lambang perusahaan saat ini">
      </div>
      <label class="field">Ganti Lambang (opsional)</label>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg">
      @error('logo')<div class="ket-file" style="color:var(--merah)">{{ $message }}</div>@enderror
      <div class="ket-file">PNG, JPG, atau SVG — maksimal 2 MB. Kosongkan bila tidak ingin mengganti.</div>
    </div>

    <div class="grup-input">
      <label class="field">Nama Perusahaan</label>
      <input type="text" name="company_name" value="{{ old('company_name', $settings->company_name) }}" required>
      @error('company_name')<div class="ket-file" style="color:var(--merah)">{{ $message }}</div>@enderror
    </div>

    <button type="submit" class="utama">Simpan Pengaturan</button>
  </form>
</div>
@endsection
