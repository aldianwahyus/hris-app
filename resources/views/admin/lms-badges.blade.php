@extends('layouts.app')

@section('judul', 'Lencana (Badge)')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil select{padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Lencana (badge)</h2>
  <p>Gamifikasi — dipetakan manual oleh HC (BRD §5.8)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.badges.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode</label>
      <input type="text" name="code" required maxlength="30" placeholder="mis. TOP-LEARNER" style="width:140px">
    </div>
    <div class="bidang-kecil">
      <label>Ikon (emoji)</label>
      <input type="text" name="icon" maxlength="10" placeholder="🏆" style="width:70px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Nama</label>
      <input type="text" name="name" required maxlength="150">
    </div>
    <div class="bidang-kecil" style="flex:2;min-width:220px">
      <label>Deskripsi</label>
      <input type="text" name="description">
    </div>
    <button type="submit" class="mini utama">Tambah Lencana</button>
  </form>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.badges.award') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="min-width:180px">
      <label>Pegawai</label>
      <select name="employee_id" required>
        <option value="">— Pilih —</option>
        @foreach ($employees as $e)
          <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->nrp }})</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil" style="min-width:180px">
      <label>Lencana</label>
      <select name="badge_id" required>
        <option value="">— Pilih —</option>
        @foreach ($badges as $b)
          <option value="{{ $b->id }}">{{ $b->icon }} {{ $b->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Catatan</label>
      <input type="text" name="notes">
    </div>
    <button type="submit" class="mini utama">Beri Lencana</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Ikon</th><th>Kode</th><th>Nama</th><th>Deskripsi</th><th>Diberikan Kepada</th></tr>
    </thead>
    <tbody>
      @forelse ($badges as $b)
        <tr>
          <td>{{ $b->icon }}</td>
          <td class="angka">{{ $b->code }}</td>
          <td>{{ $b->name }}</td>
          <td>{{ $b->description }}</td>
          <td class="angka">{{ $awardCounts[$b->id] ?? 0 }} pegawai</td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada lencana.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
