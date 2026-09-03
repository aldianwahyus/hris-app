@extends('layouts.app')

@section('judul', 'Aset Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Aset saya</h2>
  <p>Aset perusahaan yang sedang dipegang atas nama Anda</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Merek/Model</th><th>No. Seri</th><th>Sejak</th></tr>
    </thead>
    <tbody>
      @forelse ($assignments as $a)
        <tr>
          <td class="angka">{{ $a->asset_code }}</td>
          <td>{{ $a->name }}</td>
          <td>{{ $a->category }}</td>
          <td>{{ $a->brand_model ?? '—' }}</td>
          <td>{{ $a->serial_number ?? '—' }}</td>
          <td class="angka">{{ date('j M Y', strtotime($a->assigned_at)) }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Tidak ada aset yang sedang Anda pegang.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
