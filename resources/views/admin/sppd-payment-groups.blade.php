@extends('layouts.app')

@section('judul', 'Pembayaran SPPD Massal')
@section('peran', 'Admin HC / Admin SDM')

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
.mini{padding:5px 10px;border-radius:6px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks)}
.mini:hover{background:var(--latar)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pembayaran SPPD Massal — {{ $lingkup }}</h2>
  <p>Grup memo yang masih punya pegawai berstatus disetujui dan belum dibayar.</p>
</div>

@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr><th>Nomor Grup</th><th>Nomor Memo</th><th>Tujuan</th><th>Belum Dibayar</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($groups as $g)
        <tr>
          <td class="angka">{{ $g->group_number }}</td>
          <td>{{ $g->memo_number }}</td>
          <td>{{ $g->destination }}</td>
          <td class="angka">{{ $g->jumlah_belum_dibayar }} pegawai</td>
          <td>
            <a href="{{ route($g->payer_scope === 'hc' ? 'admin.sppd-payment.queue' : 'hr.sppd-payment.queue', $g->id) }}" class="mini">Proses</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Tidak ada grup memo yang menunggu pembayaran.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
