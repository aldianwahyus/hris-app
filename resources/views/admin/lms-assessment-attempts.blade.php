@extends('layouts.app')

@section('judul', 'Hasil Asesmen')
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
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.gagal{background:#FBE3E3;color:#9B2C2C}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Hasil asesmen — {{ $assessment->title }}</h2>
  <p>Laporan hasil seluruh percobaan (BRD §5.4)</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Peserta</th><th>Status</th><th>Skor</th><th>Kelulusan</th><th>Dikirim</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($attempts as $a)
        <tr>
          <td class="peg">{{ $a->full_name }}<small>{{ $a->nrp }}</small></td>
          <td><span class="tag">{{ $a->status }}</span></td>
          <td class="angka">{{ $a->total_score ?? '—' }}</td>
          <td>
            @if ($a->passed === null)
              <span style="color:var(--teks-lemah);font-size:11.5px">—</span>
            @elseif ($a->passed)
              <span class="tag lulus">Lulus</span>
            @else
              <span class="tag gagal">Tidak Lulus</span>
            @endif
          </td>
          <td class="angka">{{ $a->submitted_at ? date('j M Y H:i', strtotime($a->submitted_at)) : '—' }}</td>
          <td>
            @if ($a->status === 'submitted')
              <a href="{{ route('lms.admin.assessments.grade', $a->id) }}" class="mini">Nilai Esai</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Belum ada yang mengerjakan asesmen ini.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
