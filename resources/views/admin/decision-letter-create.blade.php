@extends('layouts.app')

@section('judul', 'Buat SK')
@section('peran', $bankWide ? 'Admin Sistem (IT)' : 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.picker{border:1px solid var(--garis);border-radius:var(--r);overflow:hidden}
.picker-alat{display:flex;gap:10px;align-items:center;padding:10px 12px;
  background:var(--latar);border-bottom:1px solid var(--garis)}
.picker-alat input[type=text]{flex:1;width:auto;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.picker-alat label{display:flex;align-items:center;gap:6px;font-size:12px;white-space:nowrap}
.picker-daftar{max-height:340px;overflow-y:auto}
.picker-baris{display:flex;align-items:center;gap:10px;padding:9px 12px;
  border-bottom:1px solid var(--garis);font-size:12.5px}
.picker-baris:last-child{border-bottom:0}
.picker-baris small{display:block;color:var(--teks-lemah);font-size:11px;margin-top:1px}
.picker-hitung{padding:8px 12px;font-size:11.5px;color:var(--teks-lemah);background:var(--latar);
  border-top:1px solid var(--garis)}
/* .bidang input{width:100%} (tata letak bersama layouts/app.blade.php)
   ikut menimpa checkbox di dalam picker ini karena sama-sama nested di
   dalam .bidang — checkbox jadi melebar sepenuh baris alih-alih ukuran
   normalnya, persis penyebab tampilan berantakan yang dilaporkan. */
.picker input[type=checkbox]{width:16px;height:16px;padding:0;flex-shrink:0;accent-color:var(--hijau)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Buat Surat Keputusan</h2>
  <p>Berlaku untuk satu pegawai atau banyak sekaligus — centang pegawai yang dituju di bawah.</p>
</div>

@if ($errors->any())
  <div class="pesan gagal">{{ $errors->first() }}</div>
@endif

<div class="kartu">
  <div class="kartu-judul">Detail SK</div>

  <form method="POST" action="{{ route('sk.store') }}" enctype="multipart/form-data" id="form-sk">
    @csrf

    <div class="baris-bidang">
      <div class="bidang">
        <label>Jenis SK</label>
        <select name="sk_type" id="sk-jenis" required onchange="document.getElementById('sk-target-mutasi').style.display = this.value === 'mutasi' ? 'flex' : 'none'; document.getElementById('sk-target-promosi').style.display = this.value === 'promosi' ? 'flex' : 'none';">
          <option value="">— Pilih —</option>
          <option value="mutasi" @selected(old('sk_type') === 'mutasi')>Mutasi</option>
          <option value="promosi" @selected(old('sk_type') === 'promosi')>Promosi</option>
          <option value="sanksi" @selected(old('sk_type') === 'sanksi')>Sanksi</option>
          <option value="lainnya" @selected(old('sk_type') === 'lainnya')>Lainnya</option>
        </select>
        <p style="font-size:11px;color:var(--teks-lemah);margin-top:6px">Untuk perubahan gaji, gunakan <a href="{{ route('sk.salary-change.create') }}" style="color:var(--hijau);font-weight:600">Buat SK Perubahan Gaji</a>.</p>
      </div>
      <div class="bidang">
        <label>Nomor SK</label>
        <input type="text" name="sk_number" maxlength="100" value="{{ old('sk_number') }}" required>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Tanggal SK</label>
        <input type="date" name="sk_date" value="{{ old('sk_date') }}" required>
      </div>
      <div class="bidang">
        <label>Tanggal Efektif (opsional)</label>
        <input type="date" name="effective_date" value="{{ old('effective_date') }}">
      </div>
    </div>

    <div class="baris-bidang" id="sk-target-mutasi" style="display:{{ old('sk_type') === 'mutasi' ? 'flex' : 'none' }}">
      <div class="bidang">
        <label>Kantor Tujuan</label>
        <select name="target_office_id">
          <option value="">— Pilih —</option>
          @foreach ($offices as $o)
            <option value="{{ $o->id }}" @selected(old('target_office_id') === $o->id)>{{ $o->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="baris-bidang" id="sk-target-promosi" style="display:{{ old('sk_type') === 'promosi' ? 'flex' : 'none' }}">
      <div class="bidang">
        <label>Jabatan Tujuan</label>
        <select name="target_position_id">
          <option value="">— Pilih —</option>
          @foreach ($positions as $p)
            <option value="{{ $p->id }}" @selected(old('target_position_id') === $p->id)>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label>Grade Pegawai (opsional)</label>
        <input type="number" name="target_person_grade" value="{{ old('target_person_grade') }}">
      </div>
      <div class="bidang">
        <label>Grade Jabatan (opsional)</label>
        <input type="number" name="target_job_grade" value="{{ old('target_job_grade') }}">
      </div>
    </div>

    <div class="bidang">
      <label>Keterangan</label>
      <textarea name="description" rows="2" required>{{ old('description') }}</textarea>
    </div>
    <div class="bidang">
      <label>Berkas SK (opsional, PDF/JPG/PNG, maks 10MB) — satu berkas berlaku untuk semua pegawai yang dicentang</label>
      <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
    </div>

    <div class="bidang">
      <label>Pegawai Tujuan</label>
      <div class="picker">
        <div class="picker-alat">
          <input type="text" id="cari-pegawai" placeholder="Cari nama/NRP...">
          <label><input type="checkbox" id="pilih-semua"> Pilih Semua</label>
        </div>
        <div class="picker-daftar" id="daftar-pegawai">
          @forelse ($employees as $e)
            <div class="picker-baris" data-cari="{{ strtolower($e->full_name.' '.$e->nrp) }}">
              <input type="checkbox" name="employee_ids[]" value="{{ $e->id }}" class="cek-pegawai">
              <div>
                {{ $e->full_name }}
                <small>{{ $e->nrp }} — {{ $e->position_name }}{{ $bankWide && isset($e->office_name) ? ' — '.$e->office_name : '' }}</small>
              </div>
            </div>
          @empty
            <div class="picker-baris">Tidak ada pegawai.</div>
          @endforelse
        </div>
        <div class="picker-hitung"><span id="jumlah-terpilih">0</span> pegawai dicentang</div>
      </div>
    </div>

    <div class="aksi" style="display:flex;gap:8px;margin-top:14px">
      <button type="submit" class="btn">Simpan SK</button>
      <a href="{{ route('sk.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection

@section('skrip')
<script>
(function () {
  var cariInput = document.getElementById('cari-pegawai');
  var pilihSemua = document.getElementById('pilih-semua');
  var baris = document.querySelectorAll('.picker-baris[data-cari]');
  var jumlahEl = document.getElementById('jumlah-terpilih');
  var form = document.getElementById('form-sk');

  function perbaruiJumlah() {
    var n = document.querySelectorAll('.cek-pegawai:checked').length;
    jumlahEl.textContent = n;
  }

  cariInput.addEventListener('input', function () {
    var kata = cariInput.value.toLowerCase().trim();
    baris.forEach(function (b) {
      var cocok = b.getAttribute('data-cari').indexOf(kata) !== -1;
      b.style.display = cocok ? 'flex' : 'none';
    });
  });

  pilihSemua.addEventListener('change', function () {
    baris.forEach(function (b) {
      if (b.style.display === 'none') { return; }
      var cek = b.querySelector('.cek-pegawai');
      if (cek) { cek.checked = pilihSemua.checked; }
    });
    perbaruiJumlah();
  });

  document.getElementById('daftar-pegawai').addEventListener('change', function (e) {
    if (e.target.classList.contains('cek-pegawai')) { perbaruiJumlah(); }
  });

  form.addEventListener('submit', function (e) {
    if (document.querySelectorAll('.cek-pegawai:checked').length === 0) {
      e.preventDefault();
      alert('Pilih minimal satu pegawai.');
    }
  });
})();
</script>
@endsection
