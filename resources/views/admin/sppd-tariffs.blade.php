@extends('layouts.app')

@section('judul', 'Tarif SPPD')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);margin-top:16px}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
select, input{font-family:inherit}
@endsection

@section('isi')
<div class="info">
  Isi Jenjang Jabatan HANYA untuk kategori berbasis jabatan, atau Pita Radius HANYA untuk
  Jarak Pendek — kosongkan yang tidak relevan. Nilai lama otomatis ditutup, bukan ditimpa.
</div>

<div class="kartu">
  <div class="kartu-judul">Tambah/ubah tarif SPPD</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('sysadmin.sppd-tariffs.store') }}"
    data-confirm="Nilai aktif untuk kombinasi ini akan ditutup dan digantikan. Lanjutkan?">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label for="component">Komponen</label>
        <select id="component" name="component" required>
          @foreach ($components as $c)
            <option value="{{ $c->value }}" @selected(old('component') === $c->value)>{{ $c->label() }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="trip_category">Kategori Perjalanan</label>
        <select id="trip_category" name="trip_category" required>
          @foreach ($categories as $cat)
            <option value="{{ $cat->value }}" @selected(old('trip_category') === $cat->value)>{{ $cat->label() }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label for="jabatan_tier">Jenjang Jabatan (kosongkan untuk Jarak Pendek)</label>
        <select id="jabatan_tier" name="jabatan_tier">
          <option value="">—</option>
          @foreach ($tiers as $t)
            <option value="{{ $t->value }}" @selected(old('jabatan_tier') === $t->value)>{{ $t->label() }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label for="radius_band">Pita Radius (hanya Jarak Pendek)</label>
        <select id="radius_band" name="radius_band">
          <option value="">—</option>
          @foreach ($radiusBands as $b)
            <option value="{{ $b->value }}" @selected(old('radius_band') === $b->value)>{{ $b->label() }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label for="currency">Mata Uang</label>
        <select id="currency" name="currency" required>
          <option value="IDR" @selected(old('currency', 'IDR') === 'IDR')>IDR</option>
          <option value="USD" @selected(old('currency') === 'USD')>USD</option>
        </select>
      </div>
      <div class="bidang">
        <label for="amount">Nominal</label>
        <input type="number" id="amount" name="amount" min="0" step="1" value="{{ old('amount') }}" required>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label for="effective_from">Berlaku Sejak</label>
        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from') }}" required>
      </div>
      <div class="bidang">
        <label for="source_document">Dasar (nomor SK, opsional)</label>
        <input type="text" id="source_document" name="source_document" value="{{ old('source_document') }}">
      </div>
    </div>
    <button type="submit" class="btn">Simpan</button>
  </form>
</div>

<div class="kepala">
  <h2>Tarif aktif hari ini</h2>
  <p>{{ $rows->count() }} baris</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Komponen</th><th>Kategori</th><th>Jenjang</th><th>Radius</th><th>Nominal</th><th>Berlaku Sejak</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td>{{ \App\Modules\Sppd\Domain\SppdTariffComponent::from($r->component)->label() }}</td>
          <td>{{ \App\Modules\Sppd\Domain\TripCategory::from($r->trip_category)->label() }}</td>
          <td>{{ $r->jabatan_tier ? \App\Modules\Sppd\Domain\JabatanTier::from($r->jabatan_tier)->label() : '—' }}</td>
          <td>{{ $r->radius_band ? \App\Modules\Sppd\Domain\RadiusBand::from($r->radius_band)->label() : '—' }}</td>
          <td class="angka">{{ $r->currency === 'USD' ? '$' : 'Rp' }}{{ number_format($r->amount_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->effective_from)) }}</td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;color:var(--teks-lemah);padding:24px">Belum ada data.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
