@extends('layouts.app')

@section('judul', 'Ajukan Absen Luar Kantor')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending{background:var(--emas-muda);color:#7A5F0B}
.status.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected{background:var(--merah-muda);color:var(--merah)}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Absen Luar Kantor</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Untuk pegawai yang sedang bertugas di lapangan. Absen akan tercatat otomatis
    setelah pengajuan ini disetujui Pimpinan Kantor. Tanggal boleh mundur hingga
    7 hari ke belakang untuk menyusulkan absen yang tertinggal.
  </div>

  <form method="POST" action="{{ route('attendance.outside.store') }}">
    @csrf
    <div class="bidang">
      <label for="work_date">Tanggal</label>
      <input type="date" id="work_date" name="work_date" value="{{ old('work_date') }}" required>
    </div>

    <div class="bidang">
      <label for="reason">Alasan / Keterangan Lokasi</label>
      <textarea id="reason" name="reason" rows="3" required>{{ old('reason') }}</textarea>
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('attendance.create') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>

<div class="kartu">
  <div class="kartu-judul">Riwayat pengajuan saya</div>
  <div class="gulir">
    <table>
      <thead>
        <tr><th>Tanggal</th><th>Alasan</th><th>Status</th></tr>
      </thead>
      <tbody>
        @php $labelStatus = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak']; @endphp
        @forelse ($riwayat as $r)
          <tr>
            <td class="angka">{{ date('D, j M Y', strtotime($r->work_date)) }}</td>
            <td>{{ $r->reason }}</td>
            <td><span class="status {{ $r->status }}">{{ $labelStatus[$r->status] ?? $r->status }}</span></td>
          </tr>
        @empty
          <tr>
            <td colspan="3" class="kosong">Belum ada pengajuan absen luar kantor.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
