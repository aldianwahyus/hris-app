@extends('layouts.app')

@section('judul', 'Job Requisition')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.filter{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.filter a{padding:6px 12px;border-radius:99px;font-size:11.5px;font-weight:600;
  border:1px solid var(--garis);background:var(--putih);color:var(--teks);text-decoration:none}
.filter a.aktif{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#FAFCFB}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending{background:var(--emas-muda);color:#7A5F0B}
.status.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected{background:#EDEDED;color:#6B6B6B}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Job Requisition</h2>
    <p>Permintaan tambahan tenaga kerja dalam lingkup kewenangan Anda</p>
  </div>
  <a href="{{ route('admin.recruitment-requisition-create') }}" class="btn">+ Ajukan Requisition</a>
</div>

<div class="filter">
  <a href="{{ route('admin.recruitment-requisition-index') }}" class="{{ $statusFilter === '' ? 'aktif' : '' }}">Menunggu</a>
  <a href="{{ route('admin.recruitment-requisition-index', ['status' => 'approved']) }}" class="{{ $statusFilter === 'approved' ? 'aktif' : '' }}">Disetujui</a>
  <a href="{{ route('admin.recruitment-requisition-index', ['status' => 'rejected']) }}" class="{{ $statusFilter === 'rejected' ? 'aktif' : '' }}">Ditolak</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kantor</th><th>Posisi</th><th>Headcount</th><th>Status</th><th>Diajukan</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($requisitions as $r)
        <tr>
          <td>{{ $r->office_name }}</td>
          <td>{{ $r->position_name }}</td>
          <td class="angka">{{ $r->requested_headcount }}</td>
          <td><span class="status {{ $r->status }}">{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$r->status] ?? $r->status }}</span></td>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td><a href="{{ route('admin.recruitment-requisition-show', $r->id) }}" class="tautan">Buka</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Tidak ada requisition pada kategori ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
