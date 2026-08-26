@extends('layouts.app')

@section('judul', 'Challenge')
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
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Challenge</h2>
  <p>Gamifikasi — HC menandai peserta selesai untuk memberi poin (BRD §5.8)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.challenges.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Judul</label>
      <input type="text" name="title" required maxlength="200">
    </div>
    <div class="bidang-kecil">
      <label>Mulai</label>
      <input type="date" name="start_date">
    </div>
    <div class="bidang-kecil">
      <label>Selesai</label>
      <input type="date" name="end_date">
    </div>
    <div class="bidang-kecil">
      <label>Poin</label>
      <input type="number" name="points_reward" required value="10" min="0" style="width:90px">
    </div>
    <button type="submit" class="mini utama">Tambah Challenge</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Periode</th><th>Poin</th><th>Peserta</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($challenges as $c)
        <tr>
          <td>{{ $c->title }}</td>
          <td class="angka">
            {{ $c->start_date ? date('j M Y', strtotime($c->start_date)) : '—' }}
            – {{ $c->end_date ? date('j M Y', strtotime($c->end_date)) : '—' }}
          </td>
          <td class="angka">{{ $c->points_reward }}</td>
          <td class="angka">{{ $participantCounts[$c->id] ?? 0 }}</td>
          <td><a href="{{ route('lms.admin.challenges.participants', $c->id) }}" class="mini">Peserta</a></td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada challenge.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
