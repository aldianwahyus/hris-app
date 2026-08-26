@extends('layouts.app')

@section('judul', 'Pembayaran Lembur — Pilih Divisi')
@section('peran', 'Admin HC')

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
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff;text-decoration:none}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pembayaran lembur — kantor pusat</h2>
  <p>Pilih divisi untuk melihat pengajuan lembur yang menunggu pembayaran</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Divisi</th><th>Jumlah Pengajuan</th><th>Total Nominal (Bruto)</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($divisions as $d)
        <tr>
          <td>{{ $d->division ?? '(Tanpa Divisi)' }}</td>
          <td class="angka">{{ $d->jumlah }}</td>
          <td class="angka">Rp {{ number_format($d->total_cents / 100, 0, ',', '.') }}</td>
          <td><a href="{{ route('admin.overtime-disbursement-queue', ['division' => $d->division]) }}" class="mini">Lihat &amp; Bayar</a></td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Tidak ada pengajuan lembur kantor pusat yang menunggu pembayaran.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
