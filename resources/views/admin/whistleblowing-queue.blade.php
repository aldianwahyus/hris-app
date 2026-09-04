@extends('layouts.app')

@section('judul', 'Whistleblowing/Pengaduan')
@section('peran', 'HR Approver')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.filter{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.filter a{padding:6px 12px;border-radius:99px;font-size:11.5px;font-weight:600;
  border:1px solid var(--garis);background:var(--putih);color:var(--teks);text-decoration:none}
.filter a.aktif{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;border-bottom:1px solid var(--garis)}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#FAFCFB}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.baru{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Whistleblowing/Pengaduan</h2>
  <p>Laporan dugaan pelanggaran — akses TERBATAS hr_approver</p>
</div>

<div class="filter">
  <a href="{{ route('admin.whistleblowing-queue') }}" class="{{ $statusFilter === '' ? 'aktif' : '' }}">Baru/Diproses</a>
  <a href="{{ route('admin.whistleblowing-queue', ['status' => 'selesai']) }}" class="{{ $statusFilter === 'selesai' ? 'aktif' : '' }}">Selesai</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pelapor</th><th>Kategori</th><th>Status</th><th>Diajukan</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($reports as $r)
        <tr>
          <td class="peg">
            @if ($r->is_anonymous)
              Anonim
            @else
              {{ $r->full_name ?? '—' }}<small>{{ $r->nrp ?? '' }}</small>
            @endif
          </td>
          <td>{{ $categories[$r->category] ?? $r->category }}</td>
          <td><span class="status {{ $r->status }}">{{ ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai'][$r->status] ?? $r->status }}</span></td>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td><a href="{{ route('admin.whistleblowing-show', $r->id) }}" class="tautan">Buka</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Tidak ada laporan pada kategori ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
