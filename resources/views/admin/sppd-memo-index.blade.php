@extends('layouts.app')

@section('judul', 'SPPD Massal')
@section('peran', $bankWide ? 'Admin HC' : 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px}
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
  <div>
    <h2>SPPD Massal</h2>
    <p>{{ $bankWide ? 'Seluruh kantor (bank-wide)' : 'Lingkup kantor Anda' }} — input berdasarkan memo divisi, langsung disetujui.</p>
  </div>
  <a href="{{ route('sppd-memo.create') }}" class="btn">+ Input Memo Baru</a>
</div>

@if (session('sukses'))
  <div class="pesan sukses">{{ session('sukses') }}</div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr><th>Nomor Grup</th><th>Nomor Memo</th><th>Tujuan</th><th>Tanggal</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($groups as $g)
        <tr>
          <td class="angka">{{ $g->group_number }}</td>
          <td>{{ $g->memo_number }}</td>
          <td>{{ $g->destination }}</td>
          <td class="angka">{{ date('j M Y', strtotime($g->start_date)) }} – {{ date('j M Y', strtotime($g->end_date)) }}</td>
          <td><a href="{{ route('sppd-memo.show', $g->id) }}" class="mini">Detail</a></td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada SPPD massal.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
