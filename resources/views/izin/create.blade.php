@extends('layouts.app')

@section('judul', 'Ajukan Izin Tidak Masuk Bekerja')
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
.tautan{color:var(--hijau);font-weight:600;text-decoration:none}
.tautan:hover{text-decoration:underline}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Izin Tidak Masuk Bekerja</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Izin <strong>TIDAK memotong saldo Cuti Tahunan</strong> — terpisah sepenuhnya dari kantong
    cuti. Langsung ke Atasan Langsung untuk diputuskan (satu tahap). Kategori
    <strong>Sakit wajib menyertakan lampiran bukti</strong> (mis. foto surat dokter).
  </div>

  <form method="POST" action="{{ route('izin.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="bidang">
      <label for="category">Kategori</label>
      <select id="category" name="category" required>
        @foreach ($categories as $c)
          <option value="{{ $c->value }}" @selected(old('category') === $c->value)>{{ $c->label() }}</option>
        @endforeach
      </select>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="start_date">Tanggal Mulai</label>
        @if ($isAdminHc)
          <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
        @else
          <input type="date" id="start_date" name="start_date"
            value="{{ old('start_date', $todayOffice) }}"
            min="{{ $todayOffice }}" max="{{ $todayOffice }}" required>
        @endif
      </div>
      <div class="bidang">
        <label for="end_date">Tanggal Selesai</label>
        <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}"
          min="{{ $isAdminHc ? '' : $todayOffice }}" required>
      </div>
    </div>
    @unless ($isAdminHc)
      <div class="info" style="margin-top:-4px">
        Tanggal mulai izin wajib hari ini (tidak dapat mundur/maju) — hanya Admin HC yang dapat memilih tanggal bebas.
      </div>
    @endunless

    <div class="bidang">
      <label for="reason">Alasan</label>
      <textarea id="reason" name="reason" rows="3" required>{{ old('reason') }}</textarea>
    </div>

    <div class="bidang">
      <label for="attachment">Lampiran Bukti (wajib untuk Sakit — JPG/PNG/PDF, maks 5 MB)</label>
      <input type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.pdf">
    </div>

    <div class="aksi">
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('ess.dashboard') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>

<div class="kartu">
  <div class="kartu-judul">Riwayat pengajuan saya</div>
  <div class="gulir">
    <table>
      <thead>
        <tr><th>Kategori</th><th>Tanggal</th><th>Alasan</th><th>Lampiran</th><th>Status</th></tr>
      </thead>
      <tbody>
        @php
          $labelKategori = ['sakit' => 'Sakit', 'keperluan_keluarga' => 'Keperluan Keluarga', 'lainnya' => 'Lainnya'];
          $labelStatus = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
        @endphp
        @forelse ($riwayat as $r)
          <tr>
            <td>{{ $labelKategori[$r->category] ?? $r->category }}</td>
            <td class="angka">
              {{ date('j M Y', strtotime($r->start_date)) }}
              @if ($r->start_date !== $r->end_date)
                — {{ date('j M Y', strtotime($r->end_date)) }}
              @endif
            </td>
            <td>{{ $r->reason }}</td>
            <td>
              @if ($r->attachment_path)
                <a href="{{ route('izin.attachment', $r->id) }}" class="tautan">Unduh</a>
              @else
                —
              @endif
            </td>
            <td><span class="status {{ $r->status }}">{{ $labelStatus[$r->status] ?? $r->status }}</span></td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="kosong">Belum ada pengajuan izin.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
