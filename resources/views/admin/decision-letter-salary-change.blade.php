@extends('layouts.app')

@section('judul', 'Buat SK Perubahan Gaji')
@section('peran', $bankWide ? 'Admin Sistem (IT)' : 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.alat{display:flex;gap:10px;align-items:center;margin-bottom:12px}
.alat input[type=text]{flex:1;max-width:320px;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;color:var(--teks-lemah);padding:9px 10px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
thead th.baru{color:var(--hijau-tua);background:var(--hijau-muda)}
tbody td{padding:8px 10px;border-bottom:1px solid var(--garis);font-size:12px;vertical-align:middle;white-space:nowrap}
tbody td.baru{background:#F5FBF8}
tbody tr:last-child td{border-bottom:0}
tbody input{width:90px;padding:6px 8px;border:1px solid var(--garis);border-radius:6px;
  font-family:inherit;font-size:12px}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:10.5px;margin-top:1px}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
.aksi{display:flex;gap:8px;margin-top:14px}
.impor-catatan{font-size:11.5px;color:var(--teks-lemah);margin-bottom:12px;line-height:1.6}
@endsection

@section('isi')
<div class="kepala">
  <h2>Buat SK Perubahan Gaji</h2>
  <p>Gaji saat ini ditampilkan sebagai acuan — isi kolom "Baru" HANYA untuk pegawai yang berubah, sisanya boleh dikosongkan.</p>
</div>

@if ($errors->any())
  <div class="pesan gagal">{{ $errors->first() }}</div>
@endif

<div class="kartu">
  <div class="kartu-judul">Isi manual per pegawai</div>

  <form method="POST" action="{{ route('sk.salary-change.store') }}" enctype="multipart/form-data" id="form-gaji">
    @csrf

    <div class="baris-bidang">
      <div class="bidang">
        <label>Nomor SK</label>
        <input type="text" name="sk_number" maxlength="100" value="{{ old('sk_number') }}" required>
      </div>
      <div class="bidang">
        <label>Tanggal SK</label>
        <input type="date" name="sk_date" value="{{ old('sk_date') }}" required>
      </div>
      <div class="bidang">
        <label>Tanggal Efektif (opsional)</label>
        <input type="date" name="effective_date" value="{{ old('effective_date') }}">
      </div>
    </div>
    <div class="bidang">
      <label>Keterangan</label>
      <textarea name="description" rows="2" required>{{ old('description') }}</textarea>
    </div>
    <div class="bidang">
      <label>Berkas SK (opsional, PDF/JPG/PNG, maks 10MB) — satu berkas berlaku untuk semua pegawai yang diisi perubahannya</label>
      <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
    </div>

    <div class="alat">
      <input type="text" id="cari-pegawai" placeholder="Cari nama/NRP...">
    </div>

    <div class="gulir">
      <table>
        <thead>
          <tr>
            <th>Pegawai</th>
            <th>Golongan Saat Ini</th><th>Step Saat Ini</th><th>Tunj. Jabatan Saat Ini</th><th>Tunj. Penyesuaian Saat Ini</th>
            <th class="baru">Golongan Baru</th><th class="baru">Step Baru</th><th class="baru">Tunj. Jabatan Baru (Rp)</th><th class="baru">Tunj. Penyesuaian Baru (Rp)</th>
          </tr>
        </thead>
        <tbody id="daftar-pegawai">
          @forelse ($employees as $e)
            <tr data-cari="{{ strtolower($e->full_name.' '.$e->nrp) }}">
              <td class="peg">{{ $e->full_name }}<small>{{ $e->nrp }} — {{ $e->position_name }}{{ $bankWide && isset($e->office_name) ? ' — '.$e->office_name : '' }}</small></td>
              <td class="angka">{{ $e->person_grade ?? '—' }}</td>
              <td class="angka">{{ $e->salary_step ?? '—' }}</td>
              <td class="angka">Rp{{ number_format($e->tunjangan_jabatan_cents / 100, 0, ',', '.') }}</td>
              <td class="angka">Rp{{ number_format($e->tunjangan_penyesuaian_cents / 100, 0, ',', '.') }}</td>
              <td class="baru"><input type="number" name="changes[{{ $e->id }}][person_grade]" value="{{ old("changes.{$e->id}.person_grade") }}"></td>
              <td class="baru"><input type="number" name="changes[{{ $e->id }}][salary_step]" min="1" value="{{ old("changes.{$e->id}.salary_step") }}"></td>
              <td class="baru"><input type="number" name="changes[{{ $e->id }}][tunjangan_jabatan]" min="0" value="{{ old("changes.{$e->id}.tunjangan_jabatan") }}"></td>
              <td class="baru"><input type="number" name="changes[{{ $e->id }}][tunjangan_penyesuaian]" min="0" value="{{ old("changes.{$e->id}.tunjangan_penyesuaian") }}"></td>
            </tr>
          @empty
            <tr><td colspan="9" class="kosong">Tidak ada pegawai.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Simpan Perubahan Gaji</button>
      <a href="{{ route('sk.index') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>

<div class="kartu" style="margin-top:20px">
  <div class="kartu-judul">Impor massal lewat berkas (Excel/CSV)</div>
  <p class="impor-catatan">
    Unduh <a href="{{ route('sk.salary-change.template') }}" style="color:var(--hijau);font-weight:600">template</a> —
    berisi seluruh pegawai dalam lingkup Anda beserta gaji saat ini. Isi kolom "_baru" HANYA untuk pegawai yang berubah
    (boleh dikosongkan untuk pegawai lain), lalu unggah kembali di bawah ini. Satu SK berlaku untuk seluruh baris yang diisi.
  </p>

  <form method="POST" action="{{ route('sk.salary-change.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nomor SK</label>
        <input type="text" name="sk_number" maxlength="100" required>
      </div>
      <div class="bidang">
        <label>Tanggal SK</label>
        <input type="date" name="sk_date" required>
      </div>
      <div class="bidang">
        <label>Tanggal Efektif (opsional)</label>
        <input type="date" name="effective_date">
      </div>
    </div>
    <div class="bidang">
      <label>Keterangan</label>
      <textarea name="description" rows="2" required></textarea>
    </div>
    <div class="bidang">
      <label>Berkas SK (opsional, PDF/JPG/PNG, maks 10MB)</label>
      <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
    </div>
    <div class="bidang">
      <label>Berkas Template Terisi (CSV, maks 10MB)</label>
      <input type="file" name="berkas" accept=".csv,.txt" required>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Impor Perubahan Gaji</button>
    </div>
  </form>
</div>
@endsection

@section('skrip')
<script>
(function () {
  var cariInput = document.getElementById('cari-pegawai');
  var baris = document.querySelectorAll('#daftar-pegawai tr[data-cari]');

  cariInput.addEventListener('input', function () {
    var kata = cariInput.value.toLowerCase().trim();
    baris.forEach(function (b) {
      var cocok = b.getAttribute('data-cari').indexOf(kata) !== -1;
      b.style.display = cocok ? '' : 'none';
    });
  });
})();
</script>
@endsection
