@extends('layouts.app')

@section('judul', 'Penugasan Shift')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil select{padding:8px 10px;border:1px solid var(--garis);border-radius:7px;
  font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff}
.mini:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Penugasan shift</h2>
  <p>Menugaskan pegawai ke pola shift — penugasan lama otomatis ditutup saat penugasan baru dibuat</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.shift-assignments.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Pegawai</label>
      <select name="employee_id" required>
        <option value="">— Pilih pegawai —</option>
        @foreach ($employees as $e)
          <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->nrp }})</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:160px">
      <label>Pola Shift</label>
      <select name="shift_pattern_id" required>
        <option value="">— Pilih pola —</option>
        @foreach ($patterns as $p)
          <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Berlaku Sejak</label>
      <input type="date" name="effective_from" required>
    </div>
    <button type="submit" class="mini">Tugaskan</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Kantor</th><th>Pola Shift</th><th>Berlaku Sejak</th><th>Berlaku Sampai</th></tr>
    </thead>
    <tbody>
      @forelse ($assignments as $a)
        <tr>
          <td>{{ $a->full_name }} <span class="angka" style="color:var(--teks-lemah)">({{ $a->nrp }})</span></td>
          <td>{{ $a->office_name }}</td>
          <td>{{ $a->pattern_name }}</td>
          <td class="angka">{{ date('j M Y', strtotime($a->effective_from)) }}</td>
          <td class="angka">{{ $a->effective_to ? date('j M Y', strtotime($a->effective_to)) : 'Sampai diganti' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada penugasan shift.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
