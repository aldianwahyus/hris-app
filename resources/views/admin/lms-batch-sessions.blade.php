@extends('layouts.app')

@section('judul', 'Sesi Pelatihan')
@section('peran', 'Admin HC / Admin Sistem')

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
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:inherit}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $batch->course_title }} — {{ $batch->batch_code }}</h2>
  <p>Sesi (hari pertemuan) untuk jadwal ini — absensi dicatat per sesi</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.batches.sessions.store', $batch->id) }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Sesi ke-</label>
      <input type="number" name="sequence" required min="1" style="width:80px">
    </div>
    <div class="bidang-kecil">
      <label>Tanggal</label>
      <input type="date" name="session_date" required>
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Topik (opsional)</label>
      <input type="text" name="topic" maxlength="200">
    </div>
    <button type="submit" class="mini utama">Tambah Sesi</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Sesi</th><th>Tanggal</th><th>Topik</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($sessions as $s)
        <tr>
          <td class="angka">Hari {{ $s->sequence }}</td>
          <td class="angka">{{ date('j M Y', strtotime($s->session_date)) }}</td>
          <td>{{ $s->topic ?? '—' }}</td>
          <td><a href="{{ route('lms.admin.sessions.attendance', $s->id) }}" class="mini">Absensi</a></td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada sesi untuk jadwal ini.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
