@extends('layouts.app')

@section('judul', 'Analitik Tenaga Kerja')
@section('peran', 'HR Approver')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:11px;margin-bottom:20px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:14px}
.ring .a{font-size:21px;font-weight:800;letter-spacing:-.03em}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}
.grafik-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:14px;margin-bottom:20px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:2px}
.kartu p.ket{font-size:11.5px;color:var(--teks-lemah);margin-bottom:12px}
.catatan{background:#FFF8E8;border:1px solid var(--emas);border-radius:8px;padding:10px 12px;
  font-size:11.5px;color:#7A5F0B;margin-bottom:20px}
.sub-judul{font-size:13px;font-weight:700;margin:0 0 4px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table.tabel-risiko{width:100%;border-collapse:collapse}
table.tabel-risiko thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;border-bottom:1px solid var(--garis)}
table.tabel-risiko tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
table.tabel-risiko tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--emas-muda);color:#7A5F0B}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Analitik Tenaga Kerja</h2>
  <p>Tren headcount, turnover, masa kerja, dan keterlibatan pegawai — seluruh bank</p>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $headcountTrend[count($headcountTrend) - 1]['value'] ?? 0 }}</div>
    <div class="l">Pegawai Aktif Saat Ini</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $turnoverRate }}%</div>
    <div class="l">Turnover Rate (12 bulan)</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $averageTenure }} th</div>
    <div class="l">Rata-rata Masa Kerja</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $atRiskEmployees->count() }}</div>
    <div class="l">Indikasi Risiko Keluar</div>
  </div>
</div>

<div class="grafik-grid">
  <div class="kartu">
    <h3>Tren Headcount</h3>
    <p class="ket">Jumlah pegawai aktif per akhir bulan, 12 bulan terakhir</p>
    @include('admin._line-chart', ['points' => $headcountTrend, 'color' => 'var(--hijau-tua)'])
  </div>
  <div class="kartu">
    <h3>Tren Skor eNPS</h3>
    <p class="ket">%Promoter − %Detractor, per pelaksanaan survei eNPS yang sudah selesai</p>
    @include('admin._line-chart', ['points' => $enpsTrend, 'color' => 'var(--hijau-tua)', 'min' => -100, 'max' => 100])
  </div>
</div>

<div class="catatan">
  <strong>Indikator berbasis aturan, BUKAN prediksi machine learning.</strong>
  Daftar di bawah menandai pegawai dengan masa kerja 1–7 tahun yang belum mengambil cuti disetujui
  dalam 6 bulan terakhir — sinyal transparan yang dapat diperiksa ulang, bukan skor probabilitas.
  Sentimen survei individual sengaja TIDAK dipakai karena sebagian besar survei eNPS bersifat anonim.
</div>

<div class="sub-judul">Indikasi Risiko Keluar</div>
<div class="gulir">
  <table class="tabel-risiko">
    <thead>
      <tr><th>Pegawai</th><th>Kantor</th><th>Bergabung</th><th>Indikator</th></tr>
    </thead>
    <tbody>
      @forelse ($atRiskEmployees as $e)
        <tr>
          <td class="peg">{{ $e->full_name }}<small>{{ $e->nrp }}</small></td>
          <td>{{ $e->office_name }}</td>
          <td class="angka">{{ date('j M Y', strtotime($e->join_date)) }}</td>
          <td><span class="status">Belum cuti 6 bulan</span></td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="kosong">Tidak ada pegawai yang cocok dengan indikator ini saat ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
