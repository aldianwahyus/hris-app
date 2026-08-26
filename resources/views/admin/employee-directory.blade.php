@extends('layouts.app')

@section('judul', 'Data Pegawai')
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
tbody tr:hover{background:#FAFCFB}
.peg{font-weight:600}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.status.trainee,.status.kontrak,.status.outsource{background:var(--emas-muda);color:#7A5F0B}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Data pegawai — {{ $office->name }}</h2>
  <p>Lingkup kantor Anda (OFFICE) · perubahan menunggu persetujuan hr_approver</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>NRP</th><th>Nama</th><th>Jabatan</th><th>Status</th>
        <th>Tanggal Masuk</th><th>Person Grade</th><th>Job Grade</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($employees as $e)
        <tr>
          <td class="angka">{{ $e->nrp }}</td>
          <td class="peg">{{ $e->full_name }}</td>
          <td>{{ $e->position_name }}</td>
          <td><span class="status {{ $e->employment_status }}">{{ ucfirst($e->employment_status) }}</span></td>
          <td class="angka">{{ date('j M Y', strtotime($e->join_date)) }}</td>
          <td class="angka">{{ $e->person_grade ?? '—' }}</td>
          <td class="angka">{{ $e->job_grade ?? '—' }}</td>
          <td><a href="{{ route('hr.employees.edit', $e->id) }}" class="mini">Ubah</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="kosong">Belum ada data pegawai di kantor ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
