@extends('layouts.app')

@section('judul', 'Rekap Biaya Lembur')
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
.ring .a{font-size:25px;font-weight:800;letter-spacing:-.03em}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.judul-seksi{font-size:13px;font-weight:700;margin:24px 0 10px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.approved{background:var(--emas-muda);color:#7A5F0B}
.status.disbursed{background:#DCEEFF;color:#0B5FA5}
.ref{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px}
.ekspor:hover{background:var(--hijau-tua)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Rekap biaya lembur{{ $office ? " — {$office->name}" : ' — seluruh bank' }}</h2>
    <p>SPKL yang sudah disetujui (termasuk yang sudah dicairkan), per pegawai, bulan {{ \Carbon\Carbon::createFromFormat('Y-m', $bulan)->translatedFormat('F Y') }}</p>
  </div>
  <form method="GET" class="filter">
    <input type="month" name="bulan" value="{{ $bulan }}">
    <button type="submit">Tampilkan</button>
  </form>
  <a href="{{ route('hr.overtime-recap.export', ['bulan' => $bulan]) }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $summary['jumlah_spkl'] }}</div>
    <div class="l">SPKL disetujui</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ rtrim(rtrim(number_format((float) $summary['total_jam'], 1, ',', '.'), '0'), ',') }}</div>
    <div class="l">Total jam lembur</div>
  </div>
  <div class="ring">
    <div class="a angka">Rp{{ number_format($summary['total_biaya_cents'] / 100, 0, ',', '.') }}</div>
    <div class="l">Total biaya lembur</div>
  </div>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pegawai</th><th>Jumlah SPKL</th><th>Total jam</th><th>Total biaya</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($perPegawai as $p)
        <tr>
          <td class="peg">{{ $p->full_name }}<small>{{ $p->nrp }}</small></td>
          <td class="angka">{{ $p->jumlah_spkl }}</td>
          <td class="angka">{{ rtrim(rtrim(number_format((float) $p->total_jam, 1, ',', '.'), '0'), ',') }}</td>
          <td class="angka">Rp{{ number_format($p->total_biaya_cents / 100, 0, ',', '.') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="kosong">Belum ada SPKL disetujui pada bulan ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="judul-seksi">⏳ Menunggu Pembayaran ({{ $menunggu->count() }})</div>
<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>No. SPKL</th><th>Pegawai</th><th>Tanggal</th><th>Jam</th><th>Nominal</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($menunggu as $r)
        <tr>
          <td class="angka">{{ $r->spkl_number }}</td>
          <td class="peg">{{ $r->full_name }}<small>{{ $r->nrp }}</small></td>
          <td class="angka">{{ date('j M Y', strtotime($r->work_date)) }}</td>
          <td class="angka">{{ rtrim(rtrim(number_format((float) $r->payable_hours, 1, ',', '.'), '0'), ',') }}</td>
          <td class="angka">Rp{{ number_format($r->amount_cents / 100, 0, ',', '.') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Tidak ada SPKL yang menunggu pembayaran bulan ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="judul-seksi">✅ Sudah Dicairkan ({{ $dicairkan->count() }})</div>
<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>No. SPKL</th><th>Pegawai</th><th>Tanggal</th><th>Jam</th><th>Nominal</th><th>Referensi Pembayaran</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($dicairkan as $r)
        <tr>
          <td class="angka">{{ $r->spkl_number }}</td>
          <td class="peg">{{ $r->full_name }}<small>{{ $r->nrp }}</small></td>
          <td class="angka">{{ date('j M Y', strtotime($r->work_date)) }}</td>
          <td class="angka">{{ rtrim(rtrim(number_format((float) $r->payable_hours, 1, ',', '.'), '0'), ',') }}</td>
          <td class="angka">Rp{{ number_format($r->amount_cents / 100, 0, ',', '.') }}</td>
          <td>
            <span class="status disbursed">{{ $r->disbursement_reference }}</span>
            <div class="ref">{{ $r->disbursed_at ? date('j M Y', strtotime($r->disbursed_at)) : '—' }}</div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Belum ada SPKL yang dicairkan bulan ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
