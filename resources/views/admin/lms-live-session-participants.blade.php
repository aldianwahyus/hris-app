@extends('layouts.app')

@section('judul', 'Peserta Sesi')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
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
.tag.hadir{background:var(--hijau-muda);color:var(--hijau-tua)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala"><h2>{{ $session->title }}</h2></div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Peserta</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($participants as $p)
        <tr>
          <td class="peg">{{ $p->full_name }}<small>{{ $p->nrp }}</small></td>
          <td><span class="tag {{ $p->status === 'attended' ? 'hadir' : '' }}">{{ $p->status }}</span></td>
          <td>
            @if ($p->status !== 'attended')
              <form method="POST" action="{{ route('lms.admin.live-sessions.attend', [$session->id, $p->employee_id]) }}">
                @csrf
                <button type="submit" class="mini">Tandai Hadir</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Belum ada pegawai yang mendaftar.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
