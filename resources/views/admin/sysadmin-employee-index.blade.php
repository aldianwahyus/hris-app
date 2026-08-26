@extends('layouts.app')

@section('judul', 'Data Pegawai')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
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
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks);display:inline-block}
.mini:hover{background:var(--latar)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Data pegawai — seluruh bank</h2>
    <p>SYSADMIN sebagai pengusul (maker) BANK_WIDE — perubahan tetap menunggu persetujuan hr_approver, sama seperti usulan Admin SDM.</p>
  </div>
  <a class="mini" href="{{ route('sysadmin.employees.create') }}">+ Tambah Pegawai Baru</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>NRP</th><th>Nama</th><th>Kantor</th><th>Jabatan</th><th>Status</th><th>PG/JG</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($employees as $e)
        <tr>
          <td class="angka">{{ $e->nrp }}</td>
          <td>{{ $e->full_name }}</td>
          <td>{{ $e->office_name }}</td>
          <td>{{ $e->position_name }}</td>
          <td>{{ ucfirst($e->employment_status) }}</td>
          <td class="angka">{{ $e->person_grade }}/{{ $e->job_grade }}</td>
          <td>
            <a class="mini" href="{{ route('sysadmin.employees.edit', $e->id) }}">Ubah</a>
            <a class="mini" href="{{ route('lms.admin.employee-competency.show', $e->id) }}">Kompetensi</a>
            <a class="mini" href="{{ route('lms.admin.talent.show', $e->id) }}">Talenta</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;color:var(--teks-lemah);padding:24px">Belum ada data pegawai.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
