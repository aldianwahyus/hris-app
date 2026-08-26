@extends('layouts.app')

@section('judul', 'Impor Absensi Mesin Fingerprint')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.info code{background:#fff;padding:1px 5px;border-radius:4px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:10px 12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.diproses{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.menunggu{background:var(--emas-muda);color:#7A5F0B}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Impor absensi dari mesin fingerprint</h2>
  <p>Unggah berkas ekspor mesin — langsung disinkronkan begitu diunggah, sekaligus berjalan otomatis tiap jam</p>
</div>

<div class="info">
  Format berkas: CSV/TXT dengan baris header berisi kolom <code>pin</code> dan <code>waktu</code>
  (juga menerima <code>scanned_at</code>/<code>datetime</code>), jam dinding SETEMPAT (tanpa zona) —
  ditafsirkan menurut zona kantor yang dipilih. PIN harus sudah dipetakan ke pegawai
  (<code>emp_employees.fingerprint_device_pin</code>) agar tercatat sebagai kehadiran.
</div>

<div class="kartu">
  <div class="kartu-judul">Unggah berkas</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('sysadmin.attendance-device.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label for="office_id">Kantor pemilik mesin</label>
        <select name="office_id" id="office_id" required>
          <option value="">Pilih kantor…</option>
          @foreach ($offices as $o)
            <option value="{{ $o->id }}">{{ $o->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="berkas">Berkas CSV/TXT</label>
        <input type="file" name="berkas" id="berkas" accept=".csv,.txt" required>
      </div>
    </div>
    <button type="submit" class="btn">Unggah &amp; Sinkronkan</button>
  </form>
</div>

<div class="kepala">
  <h2>Pin tidak dikenal</h2>
  <p>Pindaian tersinkron tapi PIN belum dipetakan ke pegawai manapun</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>PIN</th><th>Jumlah</th><th>Pindai Terakhir</th></tr>
    </thead>
    <tbody>
      @forelse ($unmatchedPins as $p)
        <tr>
          <td class="angka">{{ $p->device_pin }}</td>
          <td class="angka">{{ $p->jumlah }}</td>
          <td class="angka">{{ date('j M Y H:i', strtotime($p->terakhir)) }}</td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Tidak ada PIN tidak dikenal.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="kepala" style="margin-top:16px">
  <h2>Log pindaian terbaru</h2>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>PIN</th><th>Waktu Pindai</th><th>Status</th></tr>
    </thead>
    <tbody>
      @forelse ($recentLogs as $l)
        <tr>
          <td class="angka">{{ $l->device_pin }}</td>
          <td class="angka">{{ date('j M Y H:i', strtotime($l->scanned_at)) }}</td>
          <td><span class="status {{ $l->processed_at ? 'diproses' : 'menunggu' }}">{{ $l->processed_at ? 'Diproses' : 'Menunggu' }}</span></td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Belum ada pindaian.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
