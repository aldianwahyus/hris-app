@extends('layouts.app')

@section('judul', 'Titik Ordinat Kantor')
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
tbody td{padding:8px 12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.baris-form{display:flex;gap:6px;align-items:center}
.baris-form input{width:110px;padding:6px 8px;border:1px solid var(--garis);border-radius:6px;
  font-family:inherit;font-size:12px}
.baris-form input.radius{width:80px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:11px 13px;color:var(--merah);font-size:11.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Titik ordinat kantor</h2>
  <p>Dipakai langsung oleh absensi GPS (radius geofence) — Kantor Pusat, Cabang, dan Cabang Pembantu</p>
</div>

@if ($errors->any())
  <div class="pesan gagal" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Kode</th><th>Nama Kantor</th><th>Tipe</th>
        <th>Latitude</th><th>Longitude</th><th>Radius (m)</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($offices as $o)
        <tr>
          <td class="angka">{{ $o->code }}</td>
          <td class="peg">{{ $o->name }}</td>
          <td>{{ ['head_office' => 'Kantor Pusat', 'branch' => 'Cabang', 'sub_branch' => 'Cabang Pembantu', 'functional' => 'Fungsional'][$o->office_type] ?? $o->office_type }}</td>
          <td colspan="4">
            <form method="POST" action="{{ route('sysadmin.office-geofence.update', $o->id) }}" class="baris-form">
              @csrf
              <input type="number" step="0.0000001" name="latitude" value="{{ $o->latitude }}" placeholder="Latitude" required>
              <input type="number" step="0.0000001" name="longitude" value="{{ $o->longitude }}" placeholder="Longitude" required>
              <input type="number" class="radius" min="1" name="geofence_radius_m" value="{{ $o->geofence_radius_m }}" placeholder="Radius" required>
              <button type="submit" class="mini utama">Simpan</button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" style="text-align:center;color:var(--teks-lemah);padding:24px">Belum ada data kantor.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
