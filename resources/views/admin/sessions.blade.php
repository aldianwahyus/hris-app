@extends('layouts.app')

@section('judul', 'Manajemen Sesi')
@section('peran', 'Admin Sistem (IT)')

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
.ua{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--teks-lemah)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.mini.bahaya{color:var(--merah)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Manajemen Sesi</h2>
  <p>Seluruh sesi login aktif bank-wide — cabut untuk memaksa keluar dari perangkat itu</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pengguna</th><th>Alamat IP</th><th>Perangkat/Browser</th><th>Aktivitas Terakhir</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($sessions as $s)
        <tr>
          <td class="peg">{{ $s->user_name ?? '— (tamu/tidak dikenal)' }}<small>{{ $s->nrp ?? '' }}</small></td>
          <td class="angka">{{ $s->ip_address ?? '—' }}</td>
          <td class="ua" title="{{ $s->user_agent }}">{{ $s->user_agent ?? '—' }}</td>
          <td class="angka">{{ $s->last_activity_human }}</td>
          <td>
            <form method="POST" action="{{ route('sysadmin.sessions.revoke', $s->id) }}" data-confirm="Cabut sesi ini? Pengguna akan langsung keluar.">
              @csrf
              <button type="submit" class="mini bahaya">Cabut</button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Tidak ada sesi aktif.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
