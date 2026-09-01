@extends('layouts.app')

@section('judul', 'Rekap Absensi')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;
  margin-bottom:16px;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.tab{display:flex;gap:6px;flex-wrap:wrap}
.tab a{padding:7px 14px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);
  font-size:12.5px;font-weight:600;color:var(--teks)}
.tab a.aktif{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.filter{display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.filter input,.filter select{padding:7px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.filter button{padding:7px 14px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);
  font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.hadir{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.telat{background:var(--emas-muda);color:#7A5F0B}
.status.absen{background:var(--merah-muda);color:var(--merah)}
.sumber{font-size:10.5px;color:var(--teks-lemah)}
.peringatan{color:var(--merah);font-weight:700}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px}
.ekspor:hover{background:var(--hijau-tua)}
@endsection

@section('isi')
@php
  $tipeLabel = ['head_office' => 'Kantor Pusat', 'branch' => 'Kantor Cabang', 'sub_branch' => 'Kantor Cabang Pembantu'];
  $tabLabel = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan', 'rentang' => 'Rentang Waktu'];
  $keteranganPeriode = [
    'harian' => 'satu tanggal',
    'mingguan' => 'satu minggu (Senin–Minggu)',
    'bulanan' => 'satu bulan',
    'tahunan' => 'satu tahun',
    'rentang' => 'rentang tanggal bebas',
  ][$tampilan];
  $exportParams = array_filter(array_merge(
    ['tampilan' => $tampilan, 'tipe_kantor' => $officeType],
    match ($tampilan) {
      'harian' => ['tanggal' => $tanggal],
      'mingguan' => ['minggu' => $minggu],
      'bulanan' => ['bulan' => $bulan],
      'tahunan' => ['tahun' => $tahun],
      'rentang' => ['dari' => $dari, 'sampai' => $sampai],
    },
  ));
@endphp
<div class="kepala">
  <div>
    <h2>Rekap absensi — {{ $office ? $office->name : 'Seluruh Bank' }}{{ $office ? '' : ($officeType ? ' · '.$tipeLabel[$officeType] : '') }}</h2>
    <p>{{ $office ? 'Lingkup kantor Anda (OFFICE)' : 'Lingkup BANK_WIDE — kantor pusat seluruh divisi, seluruh kantor cabang, dan kantor cabang pembantu' }} · {{ $keteranganPeriode }}</p>
  </div>
  <div class="tab">
    @foreach ($tabLabel as $key => $label)
      <a href="{{ route('hr.attendance-recap', array_filter(['tampilan' => $key === 'harian' ? null : $key, 'tipe_kantor' => $officeType])) }}"
         class="{{ $tampilan === $key ? 'aktif' : '' }}">{{ $label }}</a>
    @endforeach
    <a href="{{ route('hr.attendance-recap.export', $exportParams) }}" class="ekspor">⬇ Ekspor CSV</a>
  </div>
</div>

@unless ($office)
  <form method="GET" class="filter">
    <input type="hidden" name="tampilan" value="{{ $tampilan }}">
    @foreach (array_diff_key($exportParams, ['tampilan' => null, 'tipe_kantor' => null]) as $key => $value)
      <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <select name="tipe_kantor" onchange="this.form.submit()">
      <option value="">Semua Kantor</option>
      @foreach ($tipeLabel as $value => $label)
        <option value="{{ $value }}" @selected($officeType === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </form>
@endunless

@if ($tampilan === 'harian')
  <form method="GET" class="filter">
    <input type="hidden" name="tampilan" value="harian">
    @if ($officeType)
      <input type="hidden" name="tipe_kantor" value="{{ $officeType }}">
    @endif
    <input type="date" name="tanggal" value="{{ $tanggal }}">
    <button type="submit">Tampilkan</button>
  </form>
@elseif ($tampilan === 'mingguan')
  <form method="GET" class="filter">
    <input type="hidden" name="tampilan" value="mingguan">
    @if ($officeType)
      <input type="hidden" name="tipe_kantor" value="{{ $officeType }}">
    @endif
    <input type="week" name="minggu" value="{{ $minggu }}">
    <button type="submit">Tampilkan</button>
    <span style="font-size:11.5px;color:var(--teks-lemah)">{{ $workingDays }} hari kerja pada minggu ini</span>
  </form>
@elseif ($tampilan === 'bulanan')
  <form method="GET" class="filter">
    <input type="hidden" name="tampilan" value="bulanan">
    @if ($officeType)
      <input type="hidden" name="tipe_kantor" value="{{ $officeType }}">
    @endif
    <input type="month" name="bulan" value="{{ $bulan }}">
    <button type="submit">Tampilkan</button>
    <span style="font-size:11.5px;color:var(--teks-lemah)">{{ $workingDays }} hari kerja pada bulan ini</span>
  </form>
@elseif ($tampilan === 'tahunan')
  <form method="GET" class="filter">
    <input type="hidden" name="tampilan" value="tahunan">
    @if ($officeType)
      <input type="hidden" name="tipe_kantor" value="{{ $officeType }}">
    @endif
    <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2100" style="width:90px">
    <button type="submit">Tampilkan</button>
    <span style="font-size:11.5px;color:var(--teks-lemah)">{{ $workingDays }} hari kerja pada tahun ini</span>
  </form>
@else
  <form method="GET" class="filter">
    <input type="hidden" name="tampilan" value="rentang">
    @if ($officeType)
      <input type="hidden" name="tipe_kantor" value="{{ $officeType }}">
    @endif
    <span style="font-size:12.5px">Dari</span>
    <input type="date" name="dari" value="{{ $dari }}">
    <span style="font-size:12.5px">s.d.</span>
    <input type="date" name="sampai" value="{{ $sampai }}">
    <button type="submit">Tampilkan</button>
    <span style="font-size:11.5px;color:var(--teks-lemah)">{{ $workingDays }} hari kerja pada rentang ini</span>
  </form>
@endif

@if ($tampilan === 'harian')
  <div class="gulir">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th><th>Pegawai</th>
          @unless ($office)
            <th>Kantor</th>
          @endunless
          <th>Masuk</th><th>Istirahat</th><th>Kembali</th><th>Pulang</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        @php $labelSumber = ['gps' => 'GPS', 'fingerprint' => 'Fingerprint', 'luar_kantor' => 'Luar Kantor']; @endphp
        @forelse ($rows as $r)
          <tr>
            <td class="angka">{{ date('j M Y', strtotime($r->work_date)) }}</td>
            <td class="peg">{{ $r->full_name }} <span class="angka" style="color:var(--teks-lemah)">({{ $r->nrp }})</span></td>
            @unless ($office)
              <td>{{ $r->office_name }} <span class="sumber">({{ $tipeLabel[$r->office_type] ?? $r->office_type }})</span></td>
            @endunless
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
            <td colspan="{{ $office ? 7 : 8 }}" class="kosong">Belum ada data absensi pada tanggal ini.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@else
  <div class="gulir">
    <table>
      <thead>
        <tr>
          <th>Pegawai</th>
          @unless ($office)
            <th>Kantor</th>
          @endunless
          <th>Hadir</th><th>Terlambat</th><th>Tanpa Catatan</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($perPegawai as $p)
          <tr>
            <td class="peg">{{ $p->full_name }} <span class="angka" style="color:var(--teks-lemah)">({{ $p->nrp }})</span></td>
            @unless ($office)
              <td>{{ $p->office_name }} <span class="sumber">({{ $tipeLabel[$p->office_type] ?? $p->office_type }})</span></td>
            @endunless
            <td class="angka">{{ $p->total_hadir }}</td>
            <td class="angka">{{ $p->total_telat }}</td>
            <td class="angka {{ $p->hari_tanpa_catatan > 0 ? 'peringatan' : '' }}">{{ $p->hari_tanpa_catatan }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ $office ? 4 : 5 }}" class="kosong">Belum ada data absensi pada periode ini.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif
@endsection
