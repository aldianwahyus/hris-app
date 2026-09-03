@extends('layouts.app')

@section('judul', 'HR Helpdesk')
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
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.terbuka{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditutup{background:#EDEDED;color:#6B6B6B}
.prioritas{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.prioritas.tinggi{background:var(--merah-muda);color:var(--merah)}
.prioritas.sedang{background:var(--emas-muda);color:#7A5F0B}
.prioritas.rendah{background:#EDEDED;color:#6B6B6B}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>HR Helpdesk</h2>
    <p>Tiket bantuan dari pegawai dalam lingkup kewenangan Anda</p>
  </div>
</div>

<div class="filter">
  <a href="{{ route('admin.helpdesk-queue') }}" class="{{ $statusFilter === '' ? 'aktif' : '' }}">Terbuka/Diproses</a>
  <a href="{{ route('admin.helpdesk-queue', ['status' => 'selesai']) }}" class="{{ $statusFilter === 'selesai' ? 'aktif' : '' }}">Selesai</a>
  <a href="{{ route('admin.helpdesk-queue', ['status' => 'ditutup']) }}" class="{{ $statusFilter === 'ditutup' ? 'aktif' : '' }}">Ditutup</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Tiket</th><th>Prioritas</th><th>Status</th><th>Ditugaskan</th><th>Diajukan</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($tickets as $t)
        <tr>
          <td class="peg">{{ $t->full_name }}<small>{{ $t->nrp }}</small></td>
          <td>{{ $t->subject }}<small>{{ $t->ticket_number }}</small></td>
          <td><span class="prioritas {{ $t->priority }}">{{ ucfirst($t->priority) }}</span></td>
          <td><span class="status {{ $t->status }}">{{ ['terbuka' => 'Terbuka', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'][$t->status] ?? $t->status }}</span></td>
          <td>{{ $t->assigned_name ?? '—' }}</td>
          <td class="angka">{{ date('j M Y', strtotime($t->created_at)) }}</td>
          <td><a href="{{ route('admin.helpdesk-show', $t->id) }}" class="tautan">Buka</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="kosong">Tidak ada tiket pada kategori ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
