@extends('layouts.app')

@section('judul', 'Pelatihan')
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
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pelatihan</h2>
  <p>Jadwal kelas yang masih membuka pendaftaran — pengajuan langsung ke Atasan Langsung untuk diputuskan</p>
</div>

@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

@if (!empty($recommendations))
  <div class="kartu" style="margin-bottom:16px">
    <div class="kartu-judul">Rekomendasi untuk Anda</div>
    <p style="font-size:12px;color:var(--teks-lemah);margin-bottom:10px">Berdasarkan kompetensi yang masih perlu dikembangkan untuk jabatan Anda</p>
    @foreach ($recommendations as $rec)
      <div style="padding:7px 0;border-bottom:1px solid var(--garis);font-size:12.5px">{{ $rec->title }}</div>
    @endforeach
  </div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kursus</th><th>Jadwal</th><th>Lokasi</th><th>Batas Daftar</th><th>Kuota</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($batches as $b)
        <tr>
          <td>{{ $b->course_title }}<br><small style="color:var(--teks-lemah)">{{ $b->category }}</small></td>
          <td class="angka">{{ date('j M Y', strtotime($b->start_date)) }} – {{ date('j M Y', strtotime($b->end_date)) }}</td>
          <td>{{ $b->location ?? '—' }}</td>
          <td class="angka">{{ $b->registration_deadline ? date('j M Y', strtotime($b->registration_deadline)) : '—' }}</td>
          <td class="angka">{{ $b->taken_seats }}{{ $b->capacity ? '/'.$b->capacity : '' }}</td>
          <td>
            <form method="POST" action="{{ route('lms.store') }}">
              @csrf
              <input type="hidden" name="batch_id" value="{{ $b->id }}">
              <button type="submit" class="mini">Daftar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Belum ada jadwal pelatihan yang membuka pendaftaran.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
