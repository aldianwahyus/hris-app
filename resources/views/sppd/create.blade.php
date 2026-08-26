@extends('layouts.app')

@section('judul', 'Ajukan SPPD')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.aksi{display:flex;gap:8px;margin-top:4px}
.blok-kondisional{display:none}
.blok-kondisional.tampil{display:block}
.pratinjau{background:var(--hijau-tua);color:#fff;border-radius:var(--r);padding:16px;margin-bottom:16px}
.pratinjau .judul{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.75;margin-bottom:11px}
.pratinjau .baris{display:flex;justify-content:space-between;padding:6px 0;font-size:12.5px;
  border-bottom:1px solid rgba(255,255,255,.14)}
.pratinjau .baris:last-child{border-bottom:0}
.pratinjau .baris.total{font-weight:700;font-size:14px;padding-top:11px}
.pratinjau .ket{font-size:10.5px;opacity:.7;margin-top:10px;line-height:1.6}
@endsection

@section('isi')
<div class="kartu" style="max-width:640px">
  <div class="kartu-judul">Ajukan SPPD</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Uang Makan, Uang Saku, dan plafon Hotel/Angkutan/Transportasi dihitung OTOMATIS oleh
    sistem sesuai jenjang jabatan dan kategori perjalanan (BPP/442/03/64/2026) — tidak dapat
    diisi manual. Pilih kategori dan tanggal, lalu tekan "Hitung Perkiraan" untuk melihatnya.
  </div>

  @if ($previewError)
    <div class="pesan gagal">{{ $previewError }}</div>
  @endif

  @if ($preview)
    <div class="pratinjau">
      <div class="judul">Perkiraan Anggaran</div>
      <div class="baris"><span>Uang Makan</span><span class="angka">{{ $preview->mataUang === 'USD' ? '$' : 'Rp' }}{{ number_format($preview->uangMakan->cents / 100, $preview->mataUang === 'USD' ? 2 : 0, ',', '.') }}</span></div>
      @if (! $preview->uangSaku->isZero())
        <div class="baris"><span>Uang Saku</span><span class="angka">{{ $preview->mataUang === 'USD' ? '$' : 'Rp' }}{{ number_format($preview->uangSaku->cents / 100, $preview->mataUang === 'USD' ? 2 : 0, ',', '.') }}</span></div>
      @endif
      @if ($preview->hotel)
        <div class="baris"><span>Plafon Hotel</span><span class="angka">Rp{{ number_format($preview->hotel->cents / 100, 0, ',', '.') }}</span></div>
      @endif
      @if ($preview->angkutanSetempat)
        <div class="baris"><span>Plafon Angkutan Setempat</span><span class="angka">Rp{{ number_format($preview->angkutanSetempat->cents / 100, 0, ',', '.') }}</span></div>
      @endif
      @if ($preview->transportasiTujuan)
        <div class="baris"><span>Plafon Transportasi ke Tujuan (PP, termasuk taksi bandara)</span><span class="angka">Rp{{ number_format($preview->transportasiTujuan->cents / 100, 0, ',', '.') }}</span></div>
      @endif
      <div class="baris total"><span>Total</span><span class="angka">{{ $preview->mataUang === 'USD' ? '$' : 'Rp' }}{{ number_format($preview->totalKeseluruhan()->cents / 100, $preview->mataUang === 'USD' ? 2 : 0, ',', '.') }}</span></div>
      <div class="ket">
        Hotel/Angkutan/Transportasi adalah PLAFON MAKSIMAL (at-cost) — realisasi sesuai
        tagihan sesungguhnya setelah perjalanan, bukan dibayar penuh secara otomatis.
      </div>
    </div>
  @endif

  <form method="POST" action="{{ route('sppd.store') }}" id="form-sppd">
    @csrf

    @php
      $isi = fn ($nama) => old($nama, request()->query($nama));
    @endphp

    <div class="bidang">
      <label for="trip_category">Kategori Perjalanan</label>
      <select id="trip_category" name="trip_category" required onchange="perbaruiBlokKondisional()">
        <option value="" disabled @selected(! $isi('trip_category'))>Pilih kategori…</option>
        @foreach ($tripCategories as $c)
          <option value="{{ $c->value }}" @selected($isi('trip_category') === $c->value)>{{ $c->label() }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="destination">Tujuan</label>
      <input type="text" id="destination" name="destination" value="{{ $isi('destination') }}" required maxlength="200">
    </div>

    <div class="bidang">
      <label for="purpose">Keperluan</label>
      <textarea id="purpose" name="purpose" rows="3" required maxlength="2000">{{ $isi('purpose') }}</textarea>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="start_date">Tanggal Mulai</label>
        <input type="date" id="start_date" name="start_date" value="{{ $isi('start_date') }}" required>
      </div>
      <div class="bidang">
        <label for="end_date">Tanggal Selesai</label>
        <input type="date" id="end_date" name="end_date" value="{{ $isi('end_date') }}" required>
      </div>
    </div>

    <div class="bidang blok-kondisional" data-untuk="jarak_pendek">
      <label for="radius_band">Pita Jarak Tempuh</label>
      <select id="radius_band" name="radius_band">
        <option value="" disabled @selected(! $isi('radius_band'))>Pilih pita jarak…</option>
        @foreach ($radiusBands as $b)
          <option value="{{ $b->value }}" @selected($isi('radius_band') === $b->value)>{{ $b->label() }}</option>
        @endforeach
      </select>
      <div class="ket">Jarak &lt;30 km tidak mendapat uang makan.</div>
    </div>

    <div class="aksi">
      <button type="submit" formmethod="GET" formaction="{{ route('sppd.create') }}" class="btn luar">Hitung Perkiraan</button>
      <button type="submit" class="btn">Kirim Pengajuan</button>
      <a href="{{ route('ess.dashboard') }}" class="btn luar">Batal</a>
    </div>
  </form>
</div>
@endsection

@section('skrip')
<script>
function perbaruiBlokKondisional() {
  var kategori = document.getElementById('trip_category').value;
  document.querySelectorAll('.blok-kondisional').forEach(function (blok) {
    var daftar = blok.getAttribute('data-untuk').split(',');
    blok.classList.toggle('tampil', daftar.indexOf(kategori) !== -1);
  });
}
document.addEventListener('DOMContentLoaded', perbaruiBlokKondisional);
</script>
@endsection
