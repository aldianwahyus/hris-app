@extends('layouts.app')

@section('judul', 'Pelatihan Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.gagal{background:#FBE3E3;color:#9B2C2C}
.tag.pending{background:var(--emas-muda);color:#7A5F0B}
.tag.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.rejected{background:#FBE3E3;color:#9B2C2C}
.tag.cancelled{background:var(--latar);color:var(--teks-lemah)}
.alasan{font-size:11px;color:#9B2C2C;margin-top:4px;max-width:200px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none;color:inherit}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala" style="display:flex;justify-content:space-between;align-items:flex-end">
  <div>
    <h2>Pelatihan saya</h2>
    <p>Riwayat pendaftaran pelatihan Anda</p>
  </div>
  <div style="display:flex;gap:6px">
    <a href="{{ route('lms.my-badges') }}" class="mini">🏅 Lencana Saya</a>
    <a href="{{ route('lms.leaderboard') }}" class="mini">Papan Peringkat</a>
  </div>
</div>

@if (session('sukses'))
  <div class="pesan sukses">{{ session('sukses') }}</div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kursus</th><th>Jadwal</th><th>No. Pendaftaran</th><th>Status</th><th>Kehadiran</th><th>Kelulusan</th><th>Sertifikat</th><th>Evaluasi</th></tr>
    </thead>
    <tbody>
      @php
        $labelStatus = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
      @endphp
      @forelse ($enrollments as $en)
        <tr>
          <td>{{ $en->course_title }}</td>
          <td class="angka">{{ date('j M Y', strtotime($en->start_date)) }} – {{ date('j M Y', strtotime($en->end_date)) }}</td>
          <td class="angka">{{ $en->enrollment_number }}</td>
          <td>
            <span class="tag {{ $en->status }}">{{ $labelStatus[$en->status] ?? $en->status }}</span>
            @if ($en->status === 'rejected' && ! empty($en->decision_note))
              <div class="alasan">Alasan: {{ $en->decision_note }}</div>
            @endif
            @if ($en->status === 'pending')
              <form method="POST" action="{{ route('lms.cancel', $en->id) }}" onsubmit="return confirm('Batalkan pendaftaran pelatihan ini?')" style="margin-top:6px">
                @csrf
                <button type="submit" class="mini">Batalkan</button>
              </form>
            @endif
          </td>
          <td class="angka">
            @if ($en->total_sesi > 0)
              {{ $en->hadir_sesi }}/{{ $en->total_sesi }} hari
            @else
              <span style="color:var(--teks-lemah);font-size:11.5px">—</span>
            @endif
          </td>
          <td>
            @if ($en->completion_status === 'lulus')
              <span class="tag lulus">Lulus @if($en->score) ({{ $en->score }}) @endif</span>
            @elseif ($en->completion_status === 'tidak_lulus')
              <span class="tag gagal">Tidak Lulus</span>
            @else
              <span style="color:var(--teks-lemah);font-size:11.5px">—</span>
            @endif
          </td>
          <td>
            @if ($en->completion_status === 'lulus')
              <a href="{{ route('lms.certificate', $en->id) }}" class="mini">Unduh PDF</a>
            @else
              <span style="color:var(--teks-lemah);font-size:11.5px">—</span>
            @endif
          </td>
          <td>
            @if ($en->completion_status === null)
              <span style="color:var(--teks-lemah);font-size:11.5px">—</span>
            @elseif ($en->is_evaluated)
              <span class="tag">Sudah Dinilai</span>
            @else
              <a href="{{ route('lms.evaluation.show', $en->id) }}" class="mini">Isi Evaluasi</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="kosong">Anda belum pernah mendaftar pelatihan.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
