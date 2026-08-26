@extends('layouts.app')

@section('judul', 'Papan Peringkat')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Papan peringkat</h2>
  <p>Total poin gamifikasi seluruh pegawai (BRD §5.8)</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>#</th><th>Pegawai</th><th>NRP</th><th>Total Poin</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $i => $r)
        <tr>
          <td class="angka">{{ $i + 1 }}</td>
          <td>{{ $r->full_name }}</td>
          <td class="angka">{{ $r->nrp }}</td>
          <td class="angka">{{ $r->total_poin }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada poin tercatat.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
