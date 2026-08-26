@extends('layouts.app')

@section('judul', 'Potongan Gaji')
@section('peran', 'Admin SDM')

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
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks);display:inline-block}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Potongan gaji — kantor saya</h2>
  <p>Hanya draf payroll kantor Anda yang belum di-approve Pejabat SDM. Begitu disetujui, akses ini tertutup total sampai dibuka kembali.</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Periode</th><th>Dibuat</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($runs as $r)
        <tr>
          <td class="angka">{{ date('F Y', strtotime($r->period)) }}</td>
          <td>{{ \Illuminate\Support\Carbon::parse($r->created_at)->translatedFormat('d M Y H:i') }}</td>
          <td><a class="mini" href="{{ route('hr.payroll-deduction.show', $r->id) }}">Kelola Potongan</a></td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Tidak ada draf payroll yang menunggu potongan saat ini.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
