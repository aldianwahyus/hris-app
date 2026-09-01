@extends('layouts.app')

@section('judul', 'Menu Aplikasi Mobile')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-menu{display:flex;align-items:center;gap:12px;padding:13px 4px;border-bottom:1px solid var(--garis)}
.baris-menu:last-child{border-bottom:0}
.baris-menu .label{flex:1;font-size:13px;font-weight:600}
.sakelar{position:relative;display:inline-block;width:40px;height:23px;flex-shrink:0}
.sakelar input{opacity:0;width:0;height:0}
.sakelar .slider{position:absolute;inset:0;background:var(--garis);border-radius:99px;cursor:pointer;transition:.15s}
.sakelar .slider::before{content:'';position:absolute;width:17px;height:17px;left:3px;top:3px;
  background:#fff;border-radius:50%;transition:.15s}
.sakelar input:checked + .slider{background:var(--hijau)}
.sakelar input:checked + .slider::before{transform:translateX(17px)}
.btn{margin-top:8px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Menu Aplikasi Mobile</h2>
  <p>
    Matikan menu di sini untuk menyembunyikannya dari APLIKASI MOBILE semua pegawai — bukan per
    peran, satu saklar berlaku bank-wide. Aplikasi web (halaman ini) TIDAK terpengaruh sama sekali.
    Perubahan langsung terlihat pegawai setiap kali aplikasi mobile dibuka atau kembali aktif, tanpa
    perlu logout/update aplikasi.
  </p>
</div>

<form method="POST" action="{{ route('sysadmin.mobile-menu.update') }}">
  @csrf
  <div class="kartu">
    @foreach ($items as $item)
      <div class="baris-menu">
        <span class="label">{{ $item->label }}</span>
        <label class="sakelar">
          <input type="checkbox" name="enabled_keys[]" value="{{ $item->key }}" @checked($item->is_enabled)>
          <span class="slider"></span>
        </label>
      </div>
    @endforeach
  </div>

  <button type="submit" class="btn">Simpan</button>
</form>
@endsection
