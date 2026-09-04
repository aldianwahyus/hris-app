@extends('layouts.app')

@section('judul', 'Permintaan Privasi Data')
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
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending{background:var(--emas-muda);color:#7A5F0B}
.status.reviewed{background:#DCEAFB;color:#1D4E89}
.status.completed{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected{background:var(--merah-muda);color:var(--merah)}
.aksi{display:flex;gap:6px;flex-wrap:wrap}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Permintaan Privasi Data</h2>
  <p>Peninjauan permintaan penghapusan data pribadi pegawai (UU PDP)</p>
</div>

<div class="filter">
  <a href="{{ route('admin.privacy-request-queue') }}" class="{{ $statusFilter === '' ? 'aktif' : '' }}">Menunggu</a>
  <a href="{{ route('admin.privacy-request-queue', ['status' => 'reviewed']) }}" class="{{ $statusFilter === 'reviewed' ? 'aktif' : '' }}">Ditinjau</a>
  <a href="{{ route('admin.privacy-request-queue', ['status' => 'completed']) }}" class="{{ $statusFilter === 'completed' ? 'aktif' : '' }}">Selesai</a>
  <a href="{{ route('admin.privacy-request-queue', ['status' => 'rejected']) }}" class="{{ $statusFilter === 'rejected' ? 'aktif' : '' }}">Ditolak</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Alasan</th><th>Status</th><th>Diajukan</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($requests as $r)
        <tr>
          <td class="peg">{{ $r->full_name }}<small>{{ $r->nrp }}</small></td>
          <td>{{ $r->reason }}</td>
          <td><span class="status {{ $r->status }}">{{ ['pending' => 'Menunggu', 'reviewed' => 'Ditinjau', 'rejected' => 'Ditolak', 'completed' => 'Selesai'][$r->status] ?? $r->status }}</span></td>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td>
            <div class="aksi">
              @if ($r->status === 'pending')
                <form method="POST" action="{{ route('admin.privacy-request-review', $r->id) }}" data-confirm="Tandai permintaan ini akan ditindaklanjuti?">
                  @csrf
                  <button class="mini utama" type="submit">Tinjau</button>
                </form>
                <form method="POST" action="{{ route('admin.privacy-request-reject', $r->id) }}" onsubmit="mintaAlasanTolak(this, event); return false;">
                  @csrf
                  <button class="mini" type="submit">Tolak</button>
                </form>
              @elseif ($r->status === 'reviewed')
                <form method="POST" action="{{ route('admin.privacy-request-complete', $r->id) }}" data-confirm="Tandai penghapusan/anonimisasi data sudah dituntaskan di luar sistem ini?">
                  @csrf
                  <button class="mini utama" type="submit">Tuntaskan</button>
                </form>
              @else
                &mdash;
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Tidak ada permintaan pada kategori ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
