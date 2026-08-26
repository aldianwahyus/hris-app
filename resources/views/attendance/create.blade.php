@extends('layouts.app')

@section('judul', 'Absensi')
@section('peran', 'Employee Self Service')

@section('gaya')
.status-besar{display:flex;align-items:center;gap:14px;padding:18px;border-radius:var(--r);
  margin-bottom:14px;background:var(--hijau-tua);color:#fff}
.status-besar .ic{font-size:28px}
.status-besar .jd{font-size:15px;font-weight:700}
.status-besar .sb{font-size:12px;opacity:.8;margin-top:2px}
.lokasi-info{font-size:11.5px;color:var(--teks-lemah);margin-bottom:14px;line-height:1.6}
.tombol-aksi{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.selesai{padding:12px 14px;background:var(--hijau-muda);color:var(--hijau-tua);
  border-radius:8px;font-size:12.5px;font-weight:600}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.hadir{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.telat{background:var(--emas-muda);color:#7A5F0B}
.status.absen{background:var(--merah-muda);color:var(--merah)}
.sumber{font-size:10.5px;color:var(--teks-lemah)}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@php
  // Aksi mana yang sah SEKARANG — cermin persis urutan yang ditegakkan
  // RecordGpsAttendance (server tetap sumber kebenaran; ini murni
  // menentukan tombol mana yang ditampilkan). Istirahat/Kembali
  // OPSIONAL (boleh langsung Masuk→Pulang), TAPI begitu Istirahat
  // tercatat, Pulang disembunyikan sampai Kembali juga tercatat.
  $belumMasuk = ! $todayRecord || ! $todayRecord->check_in_at;
  $sudahPulang = $todayRecord && $todayRecord->check_out_at;
  $sedangIstirahat = $todayRecord && $todayRecord->break_start_at && ! $todayRecord->break_end_at;
@endphp

@section('isi')
<div id="pesan-lokasi"></div>

<div class="kartu">
  <div class="kartu-judul">Absen hari ini</div>

  @if ($sudahPulang)
    <div class="selesai">Absensi hari ini sudah lengkap — sampai jumpa besok!</div>
  @else
    <div class="lokasi-info">
      Tombol di bawah memakai lokasi GPS perangkat Anda. Pastikan izin lokasi browser
      diaktifkan, dan Anda berada dalam radius kantor saat menekannya.
    </div>

    <form id="form-absen" method="POST" action="{{ route('attendance.store') }}">
      @csrf
      <input type="hidden" name="latitude" id="latitude">
      <input type="hidden" name="longitude" id="longitude">
      <input type="hidden" name="action" id="action-terpilih">

      <div class="tombol-aksi">
        @if ($belumMasuk)
          <button type="button" class="btn tombol-absen" data-aksi="masuk">Absen Masuk (GPS)</button>
        @elseif ($sedangIstirahat)
          <button type="button" class="btn tombol-absen" data-aksi="kembali">Kembali dari Istirahat (GPS)</button>
        @else
          <button type="button" class="btn luar tombol-absen" data-aksi="istirahat">Mulai Istirahat (GPS)</button>
          <button type="button" class="btn tombol-absen" data-aksi="pulang">Absen Pulang (GPS)</button>
        @endif
        <span id="status-lokasi" style="font-size:11.5px;color:var(--teks-lemah)"></span>
      </div>
    </form>
  @endif
</div>

<div class="kartu">
  <div class="kartu-judul">Riwayat 14 hari terakhir</div>
  <div class="gulir">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th><th>Masuk</th><th>Istirahat</th><th>Kembali</th><th>Pulang</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        @php $labelSumber = ['gps' => 'GPS', 'fingerprint' => 'Fingerprint', 'luar_kantor' => 'Luar Kantor']; @endphp
        @forelse ($riwayat as $r)
          <tr>
            <td class="angka">{{ date('D, j M Y', strtotime($r->work_date)) }}</td>
            <td class="angka">
              @if ($r->check_in_local)
                {{ $r->check_in_local }}
                <span class="sumber">({{ $labelSumber[$r->check_in_source] ?? $r->check_in_source }})</span>
              @else
                —
              @endif
            </td>
            <td class="angka">{{ $r->break_start_local ?? '—' }}</td>
            <td class="angka">{{ $r->break_end_local ?? '—' }}</td>
            <td class="angka">
              @if ($r->check_out_local)
                {{ $r->check_out_local }}
                <span class="sumber">({{ $labelSumber[$r->check_out_source] ?? $r->check_out_source }})</span>
              @else
                —
              @endif
            </td>
            <td><span class="status {{ $r->status }}">{{ ['hadir' => 'Hadir', 'telat' => 'Terlambat', 'absen' => 'Tidak Hadir'][$r->status] ?? $r->status }}</span></td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="kosong">Belum ada riwayat absensi.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@section('skrip')
<script>
document.querySelectorAll('.tombol-absen').forEach(function (tombol) {
  tombol.addEventListener('click', function () {
    var semuaTombol = document.querySelectorAll('.tombol-absen');
    var statusEl = document.getElementById('status-lokasi');

    if (!navigator.geolocation) {
      statusEl.textContent = 'Perangkat/browser ini tidak mendukung GPS.';
      return;
    }

    semuaTombol.forEach(function (t) { t.disabled = true; });
    statusEl.textContent = 'Mengambil lokasi...';

    navigator.geolocation.getCurrentPosition(function (posisi) {
      document.getElementById('latitude').value = posisi.coords.latitude;
      document.getElementById('longitude').value = posisi.coords.longitude;
      document.getElementById('action-terpilih').value = tombol.dataset.aksi;
      document.getElementById('form-absen').submit();
    }, function () {
      semuaTombol.forEach(function (t) { t.disabled = false; });
      statusEl.textContent = 'Gagal mengambil lokasi — izinkan akses GPS lalu coba lagi.';
    }, { enableHighAccuracy: true, timeout: 10000 });
  });
});
</script>
@endsection
