@extends('layouts.app')

@section('judul', 'Absensi Sesi Pelatihan')
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
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
tbody select{padding:6px 8px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:12px}
.aksi{margin-top:16px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $session->course_title }} — {{ $session->batch_code }} · Hari {{ $session->sequence }}</h2>
  <p>{{ date('j M Y', strtotime($session->session_date)) }}{{ $session->topic ? ' · '.$session->topic : '' }} — hanya pendaftar yang sudah disetujui</p>
</div>

@if ($enrollments->isEmpty())
  <div class="gulir"><div class="kosong">Belum ada pendaftar yang disetujui pada jadwal ini.</div></div>
@else
  <form method="POST" action="{{ route('lms.admin.sessions.attendance.store', $session->id) }}">
    @csrf
    <div class="gulir">
      <table>
        <thead>
          <tr><th>Peserta</th><th>Status Kehadiran</th></tr>
        </thead>
        <tbody>
          @foreach ($enrollments as $en)
            <tr>
              <td class="peg">{{ $en->full_name }}<small>{{ $en->nrp }}</small></td>
              <td>
                <select name="kehadiran[{{ $en->enrollment_id }}]" required>
                  <option value="">Pilih...</option>
                  <option value="hadir" @selected($en->attendance_status === 'hadir')>Hadir</option>
                  <option value="izin" @selected($en->attendance_status === 'izin')>Izin</option>
                  <option value="sakit" @selected($en->attendance_status === 'sakit')>Sakit</option>
                  <option value="alpa" @selected($en->attendance_status === 'alpa')>Alpa</option>
                </select>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="aksi">
      <button type="submit" class="mini">Simpan Absensi</button>
    </div>
  </form>
@endif
@endsection
