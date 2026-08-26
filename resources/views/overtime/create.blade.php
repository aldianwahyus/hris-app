@extends('layouts.app')

@section('judul', 'Ajukan Lembur')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
.pratinjau{background:var(--hijau-tua);color:#fff;border-radius:var(--r);padding:16px;margin-bottom:16px}
.pratinjau .judul{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.75;margin-bottom:11px}
.pratinjau .baris{display:flex;justify-content:space-between;padding:6px 0;font-size:12.5px;
  border-bottom:1px solid rgba(255,255,255,.14)}
.pratinjau .baris:last-child{border-bottom:0}
.pratinjau .baris.total{font-weight:700;font-size:14px;padding-top:11px}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Ajukan Lembur</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Jam lembur dihitung OTOMATIS dari catatan absensi (jam pulang aktual pada tanggal yang
    dipilih) — tidak dapat diisi manual. Plafon <strong>18 jam per minggu</strong> dan
    <strong>4 jam per hari</strong> (Lembur Biasa) dihitung dari jam hasil pembacaan absensi ini.
  </div>

  @if ($previewError)
    <div class="pesan gagal">{{ $previewError }}</div>
  @endif

  @if ($evidence)
    <div class="pratinjau">
      <div class="judul">Bukti Lembur dari Absensi</div>
      <div class="baris"><span>Jam Masuk</span><span class="angka">{{ $evidence->checkInAt->format('H:i') }}</span></div>
      <div class="baris"><span>Jam Pulang</span><span class="angka">{{ $evidence->checkOutAt->format('H:i') }}</span></div>
      <div class="baris total"><span>Jam Lembur</span><span class="angka">{{ rtrim(rtrim(number_format($evidence->hours, 1, ',', '.'), '0'), ',') }} jam</span></div>
    </div>
  @endif

  <form method="POST" action="{{ route('overtime.store') }}">
    @csrf

    @php
      $isi = fn ($nama) => old($nama, request()->query($nama));
    @endphp

    <div class="bidang">
      <label for="overtime_type">Jenis Lembur</label>
      <select id="overtime_type" name="overtime_type" required>
        <option value="" disabled @selected(! $isi('overtime_type'))>Pilih jenis…</option>
        <option value="regular" @selected($isi('overtime_type') === 'regular')>Lembur Biasa</option>
        <option value="crash_program" @selected($isi('overtime_type') === 'crash_program')>Crash Program</option>
        <option value="shift_picket" @selected($isi('overtime_type') === 'shift_picket')>Shift / Piket</option>
      </select>
    </div>

    <div class="bidang">
      <label for="work_date">Tanggal Pelaksanaan</label>
      <input type="date" id="work_date" name="work_date" value="{{ $isi('work_date') }}" required>
    </div>

    <div class="aksi">
      <button type="submit" formmethod="GET" formaction="{{ route('overtime.create') }}" class="btn luar">Cek Absensi</button>
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('ess.dashboard') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection
