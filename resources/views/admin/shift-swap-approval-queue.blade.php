@extends('layouts.app')

@section('judul', 'Antrean Persetujuan Tukar Shift')
@section('peran', 'Atasan Langsung')

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
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.aksi{display:flex;gap:6px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Antrean persetujuan tukar shift</h2>
  <p>Pengajuan dari kantor dalam lingkup kewenangan Anda</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pemohon</th><th>Tukar Dengan</th><th>Tanggal</th>
        <th>Shift Pemohon</th><th>Shift Rekan</th><th>Alasan</th><th>Tindakan</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">{{ $r->requesting_name }}</td>
          <td>{{ $r->counterpart_name }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->swap_date)) }}</td>
          <td>{{ $r->requesting_pattern_name }}</td>
          <td>{{ $r->counterpart_pattern_name }}</td>
          <td>{{ $r->reason ?? '—' }}</td>
          <td>
            @if (auth()->user()->hasRole('auditor'))
              <span style="font-size:11px;color:var(--teks-lemah)">Hanya-baca</span>
            @else
              <div class="aksi">
                <form method="POST" action="{{ route('admin.shift-swap-approve', $r->id) }}">
                  @csrf
                  <button class="mini utama" type="submit">Setujui</button>
                </form>
                <form method="POST" action="{{ route('admin.shift-swap-reject', $r->id) }}">
                  @csrf
                  <button class="mini" type="submit">Tolak</button>
                </form>
              </div>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="kosong">Tidak ada pengajuan yang menunggu keputusan.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
