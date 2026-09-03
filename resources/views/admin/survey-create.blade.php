@extends('layouts.app')

@section('judul', 'Buat Survei')
@section('peran', 'Admin SDM')

@section('gaya')
.aksi{display:flex;gap:8px;margin-top:14px}
.pertanyaan-baris{border:1px solid var(--garis);border-radius:8px;padding:12px;margin-bottom:10px;position:relative}
.pertanyaan-hapus{position:absolute;top:8px;right:8px;background:none;border:none;color:var(--merah);
  cursor:pointer;font-size:11px;font-weight:600}
.centang{display:flex;align-items:center;gap:6px;font-size:12px;margin-top:4px}
@endsection

@section('isi')
<div class="kartu" style="max-width:640px">
  <div class="kartu-judul">Buat Survei Baru</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('admin.survey-store') }}" id="form-survei">
    @csrf
    <div class="bidang">
      <label for="title">Judul</label>
      <input type="text" id="title" name="title" maxlength="200" value="{{ old('title') }}" required>
    </div>

    <div class="bidang">
      <label for="description">Deskripsi</label>
      <textarea id="description" name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="type">Jenis</label>
        <select id="type" name="type" required>
          <option value="enps" @selected(old('type') === 'enps')>eNPS</option>
          <option value="pulse" @selected(old('type') === 'pulse')>Pulse</option>
          <option value="kustom" @selected(old('type', 'kustom') === 'kustom')>Kustom</option>
        </select>
      </div>
      <div class="bidang">
        <label for="scope">Lingkup</label>
        <select id="scope" name="scope" required onchange="document.getElementById('bidang-kantor').hidden = this.value !== 'office'">
          <option value="bank_wide" @selected(old('scope', 'bank_wide') === 'bank_wide')>Seluruh Bank</option>
          <option value="office" @selected(old('scope') === 'office')>Satu Kantor</option>
        </select>
      </div>
    </div>

    <div class="bidang" id="bidang-kantor" @if(old('scope') !== 'office') hidden @endif>
      <label for="office_id">Kantor</label>
      <select id="office_id" name="office_id">
        <option value="">— Pilih Kantor —</option>
        @foreach ($offices as $o)
          <option value="{{ $o->id }}" @selected(old('office_id') === $o->id)>{{ $o->name }}</option>
        @endforeach
      </select>
    </div>

    <label class="centang">
      <input type="checkbox" name="is_anonymous" value="1" @checked(old('is_anonymous'))>
      Survei anonim (identitas pengisi tidak dicatat pada jawaban)
    </label>

    <div class="baris-bidang" style="margin-top:14px">
      <div class="bidang">
        <label for="start_date">Tanggal Mulai</label>
        <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
      </div>
      <div class="bidang">
        <label for="end_date">Tanggal Selesai</label>
        <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
      </div>
    </div>

    <label style="display:block;font-size:11.5px;font-weight:700;color:var(--teks-lemah);margin:16px 0 8px">Pertanyaan</label>
    <div id="daftar-pertanyaan"></div>
    <button type="button" class="btn luar" id="tambah-pertanyaan" style="padding:7px 12px;font-size:12px">+ Tambah Pertanyaan</button>

    <div class="aksi">
      <button type="submit" class="btn">Simpan sebagai Draf</button>
      <a href="{{ route('admin.survey-index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>

<template id="templat-pertanyaan">
  <div class="pertanyaan-baris">
    <button type="button" class="pertanyaan-hapus">Hapus</button>
    <div class="bidang">
      <label>Teks Pertanyaan</label>
      <input type="text" name="questions[__idx__][question_text]" maxlength="500" required>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Tipe Jawaban</label>
        <select name="questions[__idx__][question_type]" class="tipe-pertanyaan" required>
          <option value="nps_0_10">Skala 0-10 (eNPS)</option>
          <option value="rating_1_5">Peringkat 1-5</option>
          <option value="pilihan_ganda">Pilihan Ganda</option>
          <option value="teks">Teks Bebas</option>
        </select>
      </div>
      <div class="bidang bidang-opsi" hidden>
        <label>Opsi (pisahkan dengan koma)</label>
        <input type="text" name="questions[__idx__][options]" placeholder="Setuju, Netral, Tidak Setuju">
      </div>
    </div>
  </div>
</template>

@once
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const daftar = document.getElementById('daftar-pertanyaan');
    const templat = document.getElementById('templat-pertanyaan');
    let idx = 0;

    function tambahBaris() {
      const html = templat.innerHTML.replaceAll('__idx__', String(idx));
      const bungkus = document.createElement('div');
      bungkus.innerHTML = html.trim();
      const baris = bungkus.firstElementChild;

      const tipeSelect = baris.querySelector('.tipe-pertanyaan');
      const bidangOpsi = baris.querySelector('.bidang-opsi');
      tipeSelect.addEventListener('change', function () {
        bidangOpsi.hidden = this.value !== 'pilihan_ganda';
      });

      baris.querySelector('.pertanyaan-hapus').addEventListener('click', function () {
        baris.remove();
      });

      daftar.appendChild(baris);
      idx++;
    }

    document.getElementById('tambah-pertanyaan').addEventListener('click', tambahBaris);
    tambahBaris();

    document.getElementById('form-survei').addEventListener('submit', function (e) {
      if (daftar.children.length === 0) {
        e.preventDefault();
        if (window.Swal) {
          window.Swal.fire({ icon: 'warning', title: 'Minimal satu pertanyaan', text: 'Tambahkan minimal satu pertanyaan sebelum menyimpan.' });
        }
      }
    });
  });
</script>
@endonce
@endsection
