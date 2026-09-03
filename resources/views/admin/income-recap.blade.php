@extends('layouts.app')

@section('judul', 'Rekap Penghasilan')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;
  margin-bottom:16px;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.filter{display:flex;gap:8px;align-items:center}
.filter input{padding:7px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.filter button{padding:7px 14px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);
  font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:11px;margin-bottom:16px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:14px}
.ring .a{font-size:21px;font-weight:800;letter-spacing:-.03em}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}
.ring.total{background:var(--hijau-tua);border-color:var(--hijau-tua)}
.ring.total .a,.ring.total .l{color:#fff}
.ring.total .l{opacity:.75}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tfoot td{padding:12px;font-size:12.5px;font-weight:700;border-top:2px solid var(--garis)}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.jumlah{font-weight:700}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px}
.ekspor:hover{background:var(--hijau-tua)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Rekap penghasilan{{ $office ? " — {$office->name}" : ' — seluruh bank' }}</h2>
    <p>Gaji bersih + lembur + SPPD + bekal cuti, per pegawai, bulan {{ \Carbon\Carbon::createFromFormat('Y-m', $bulan)->translatedFormat('F Y') }}</p>
  </div>
  <form method="GET" class="filter">
    <input type="month" name="bulan" value="{{ $bulan }}">
    <button type="submit">Tampilkan</button>
  </form>
  <a href="{{ route('hr.income-recap.export', ['bulan' => $bulan]) }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $summary['jumlah_pegawai'] }}</div>
    <div class="l">Pegawai</div>
  </div>
  <div class="ring">
    <div class="a angka">Rp{{ number_format($summary['total_gaji_cents'] / 100, 0, ',', '.') }}</div>
    <div class="l">Total gaji bersih</div>
  </div>
  <div class="ring">
    <div class="a angka">Rp{{ number_format($summary['total_lembur_cents'] / 100, 0, ',', '.') }}</div>
    <div class="l">Total lembur</div>
  </div>
  <div class="ring">
    <div class="a angka">Rp{{ number_format($summary['total_sppd_cents'] / 100, 0, ',', '.') }}</div>
    <div class="l">Total SPPD</div>
  </div>
  <div class="ring">
    <div class="a angka">Rp{{ number_format($summary['total_bekal_cuti_cents'] / 100, 0, ',', '.') }}</div>
    <div class="l">Total bekal cuti</div>
  </div>
  <div class="ring total">
    <div class="a angka">Rp{{ number_format($summary['total_keseluruhan_cents'] / 100, 0, ',', '.') }}</div>
    <div class="l">Total keseluruhan</div>
  </div>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pegawai</th><th>Gaji Bersih</th><th>Lembur</th><th>SPPD</th><th>Bekal Cuti</th><th>Total Penghasilan</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">{{ $r->full_name }}<small>{{ $r->nrp }}</small></td>
          <td class="angka">Rp{{ number_format($r->gaji_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($r->lembur_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($r->sppd_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($r->bekal_cuti_cents / 100, 0, ',', '.') }}</td>
          <td class="angka jumlah">Rp{{ number_format($r->total_cents / 100, 0, ',', '.') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Belum ada data pegawai pada lingkup ini.</td>
        </tr>
      @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
      <tfoot>
        <tr>
          <td>Total</td>
          <td class="angka">Rp{{ number_format($summary['total_gaji_cents'] / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($summary['total_lembur_cents'] / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($summary['total_sppd_cents'] / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($summary['total_bekal_cuti_cents'] / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp{{ number_format($summary['total_keseluruhan_cents'] / 100, 0, ',', '.') }}</td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>
@endsection
