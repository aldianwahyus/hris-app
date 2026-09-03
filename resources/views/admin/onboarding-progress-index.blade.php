@extends('layouts.app')

@section('judul', 'Progres Onboarding')
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
.progres{display:flex;align-items:center;gap:8px}
.progres-luar{width:100px;background:var(--latar);border-radius:6px;overflow:hidden;height:10px}
.progres-dalam{background:var(--hijau);height:100%;border-radius:6px}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Progres Onboarding</h2>
    <p>Checklist onboarding pegawai baru dalam lingkup kewenangan Anda</p>
  </div>
</div>

<div class="filter">
  <a href="{{ route('admin.onboarding-index') }}" class="{{ ! $showCompleted ? 'aktif' : '' }}">Berjalan</a>
  <a href="{{ route('admin.onboarding-index', ['selesai' => 1]) }}" class="{{ $showCompleted ? 'aktif' : '' }}">Selesai</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Dimulai</th><th>Progres</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($checklists as $c)
        @php $stat = $itemStats[$c->id] ?? null; $total = $stat->total ?? 0; $selesai = $stat->selesai ?? 0; @endphp
        <tr>
          <td class="peg">{{ $c->full_name }}<small>{{ $c->nrp }}</small></td>
          <td class="angka">{{ date('j M Y', strtotime($c->started_at)) }}</td>
          <td>
            <div class="progres">
              <div class="progres-luar"><div class="progres-dalam" style="width:{{ $total > 0 ? round($selesai / $total * 100) : 0 }}%"></div></div>
              <span class="angka">{{ $selesai }}/{{ $total }}</span>
            </div>
          </td>
          <td><a href="{{ route('admin.onboarding-show', $c->id) }}" class="tautan">Buka</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="kosong">Tidak ada checklist onboarding pada kategori ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
