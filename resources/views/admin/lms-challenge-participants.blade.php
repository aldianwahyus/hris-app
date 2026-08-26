@extends('layouts.app')

@section('judul', 'Peserta Challenge')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $challenge->title }}</h2>
  <p>Poin per penyelesaian: {{ $challenge->points_reward }}</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Peserta</th><th>Status</th><th>Selesai Pada</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($participants as $p)
        <tr>
          <td class="peg">{{ $p->full_name }}<small>{{ $p->nrp }}</small></td>
          <td><span class="tag {{ $p->status === 'completed' ? 'selesai' : '' }}">{{ $p->status }}</span></td>
          <td class="angka">{{ $p->completed_at ? date('j M Y', strtotime($p->completed_at)) : '—' }}</td>
          <td>
            @if ($p->status !== 'completed')
              <form method="POST" action="{{ route('lms.admin.challenges.complete', [$challenge->id, $p->employee_id]) }}">
                @csrf
                <button type="submit" class="mini">Tandai Selesai</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada pegawai yang bergabung.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
