@extends('layouts.app')

@section('judul', 'Buat Template Checklist')
@section('peran', 'Admin SDM')

@section('gaya')
.aksi{display:flex;gap:8px;margin-top:14px}
.item-baris{border:1px solid var(--garis);border-radius:8px;padding:12px;margin-bottom:10px;position:relative}
.item-hapus{position:absolute;top:8px;right:8px;background:none;border:none;color:var(--merah);
  cursor:pointer;font-size:11px;font-weight:600}
@endsection

@section('isi')
<div class="kartu" style="max-width:600px">
  <div class="kartu-judul">Buat Template Checklist Onboarding</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('admin.onboarding-template-store') }}" id="form-template">
    @csrf
    <div class="bidang">
      <label for="name">Nama Template</label>
      <input type="text" id="name" name="name" maxlength="150" value="{{ old('name') }}" required>
    </div>

    <div class="bidang">
      <label for="employment_status_scope">Berlaku Untuk</label>
      <select id="employment_status_scope" name="employment_status_scope">
        <option value="">Semua Status Kepegawaian</option>
        @foreach ($employmentStatuses as $value => $label)
          <option value="{{ $value }}" @selected(old('employment_status_scope') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <label style="display:block;font-size:11.5px;font-weight:700;color:var(--teks-lemah);margin:16px 0 8px">Item Checklist</label>
    <div id="daftar-item"></div>
    <button type="button" class="btn luar" id="tambah-item" style="padding:7px 12px;font-size:12px">+ Tambah Item</button>

    <div class="aksi">
      <button type="submit" class="btn">Simpan Template</button>
      <a href="{{ route('admin.onboarding-template-index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>

<template id="templat-item">
  <div class="item-baris">
    <button type="button" class="item-hapus">Hapus</button>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nama Item</label>
        <input type="text" name="items[__idx__][item_name]" maxlength="200" required>
      </div>
      <div class="bidang">
        <label>Kategori</label>
        <select name="items[__idx__][category]" required>
          @foreach ($categories as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</template>

@once
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const daftar = document.getElementById('daftar-item');
    const templat = document.getElementById('templat-item');
    let idx = 0;

    function tambahBaris() {
      const html = templat.innerHTML.replaceAll('__idx__', String(idx));
      const bungkus = document.createElement('div');
      bungkus.innerHTML = html.trim();
      const baris = bungkus.firstElementChild;

      baris.querySelector('.item-hapus').addEventListener('click', function () {
        baris.remove();
      });

      daftar.appendChild(baris);
      idx++;
    }

    document.getElementById('tambah-item').addEventListener('click', tambahBaris);
    tambahBaris();

    document.getElementById('form-template').addEventListener('submit', function (e) {
      if (daftar.children.length === 0) {
        e.preventDefault();
        if (window.Swal) {
          window.Swal.fire({ icon: 'warning', title: 'Minimal satu item', text: 'Tambahkan minimal satu item checklist sebelum menyimpan.' });
        }
      }
    });
  });
</script>
@endonce
@endsection
