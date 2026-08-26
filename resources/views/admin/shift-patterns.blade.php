@extends('layouts.app')

@section('judul', 'Pola Shift')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input{padding:8px 10px;border:1px solid var(--garis);border-radius:7px;
  font-family:inherit;font-size:12.5px}
.bidang-kecil.centang{flex-direction:row;align-items:center;gap:6px;padding-bottom:8px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pola shift</h2>
  <p>Definisi jam kerja bergilir — dipakai saat menugaskan shift ke pegawai</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.shift-patterns.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode</label>
      <input type="text" name="code" required maxlength="20" placeholder="mis. PAGI" style="width:100px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:160px">
      <label>Nama</label>
      <input type="text" name="name" required maxlength="100" placeholder="mis. Shift Pagi">
    </div>
    <div class="bidang-kecil">
      <label>Jam Mulai</label>
      <input type="time" name="start_time" required>
    </div>
    <div class="bidang-kecil">
      <label>Jam Selesai</label>
      <input type="time" name="end_time" required>
    </div>
    <div class="bidang-kecil centang">
      <input type="checkbox" name="crosses_midnight" id="crosses_midnight" value="1">
      <label for="crosses_midnight" style="font-weight:500">Lintas hari</label>
    </div>
    <button type="submit" class="mini utama">Tambah</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama</th><th>Jam</th><th>Status</th></tr>
    </thead>
    <tbody>
      @forelse ($patterns as $p)
        <tr>
          <td class="angka">{{ $p->code }}</td>
          <td>{{ $p->name }}</td>
          <td class="angka">{{ substr($p->start_time, 0, 5) }}–{{ substr($p->end_time, 0, 5) }}{{ $p->crosses_midnight ? ' (+1 hari)' : '' }}</td>
          <td><span class="tag {{ $p->is_active ? 'aktif' : '' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada pola shift.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
