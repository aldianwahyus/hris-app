@extends('layouts.app')

@section('judul', 'Lowongan')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.buka{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.tutup{background:#EDEDED;color:#6B6B6B}
.aksi{display:flex;gap:6px}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.mini{padding:5px 10px;border-radius:6px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Lowongan</h2>
    <p>Seluruh lowongan yang pernah dibuka — publik dapat melamar selama berstatus "Buka"</p>
  </div>
  <a href="{{ route('admin.recruitment-posting-create') }}" class="btn">+ Buka Lowongan</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Kantor</th><th>Status</th><th>Pelamar</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($postings as $p)
        <tr>
          <td>{{ $p->title }}</td>
          <td>{{ $p->office_name }}</td>
          <td><span class="status {{ $p->is_open ? 'buka' : 'tutup' }}">{{ $p->is_open ? 'Buka' : 'Tutup' }}</span></td>
          <td class="angka">{{ $applicationCounts[$p->id] ?? 0 }}</td>
          <td>
            <div class="aksi">
              <a href="{{ route('admin.recruitment-pipeline-index', $p->id) }}" class="tautan">Pipeline</a>
              @if ($p->is_open)
                <form method="POST" action="{{ route('admin.recruitment-posting-close', $p->id) }}" data-confirm="Tutup lowongan ini?">
                  @csrf
                  <button type="submit" class="mini">Tutup</button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Belum ada lowongan yang pernah dibuka.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
