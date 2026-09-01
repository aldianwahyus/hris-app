@extends('layouts.app')

@section('judul', 'Record Pegawai')
@section('peran', 'Pejabat SDM / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.filter{display:flex;gap:8px;align-items:center;margin-bottom:16px}
.filter input{padding:7px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.filter button{padding:7px 14px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);
  font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Record Pegawai</h2>
  <p>Rincian posisi terakhir seluruh kantor per bulan — laporan otomatis dari SK Mutasi/Promosi yang disetujui, tidak ada entri manual.</p>
</div>

<form method="GET" class="filter">
  <input type="month" name="bulan" value="{{ $bulan }}">
  <button type="submit">Tampilkan</button>
</form>

@if ($sebelumRilis)
  <div class="info">
    Bulan yang dipilih SEBELUM fitur Record Pegawai dirilis ({{ \Carbon\Carbon::parse($tanggalRilis)->translatedFormat('d F Y') }}).
    Sistem sebelumnya tidak pernah merekam riwayat posisi — baris di bawah adalah PROYEKSI MUNDUR posisi pegawai
    pada saat fitur ini dirilis, BUKAN riwayat sesungguhnya pada bulan yang dipilih.
  </div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Nama</th><th>NRP</th><th>Kantor</th><th>Jabatan</th><th>Person Grade</th><th>Job Grade</th><th>Berlaku Sejak</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">{{ $r->full_name }}</td>
          <td class="angka">{{ $r->nrp }}</td>
          <td>{{ $r->office_name }}</td>
          <td>{{ $r->position_name }}</td>
          <td class="angka">{{ $r->person_grade ?? '—' }}</td>
          <td class="angka">{{ $r->job_grade ?? '—' }}</td>
          <td class="angka">{{ date('d M Y', strtotime($r->effective_from)) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="kosong">Belum ada data posisi pada bulan ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
