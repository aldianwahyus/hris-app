@extends('layouts.app')

@section('judul', 'Analitik & Laporan')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-end}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.tautan{display:flex;gap:6px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none}
.mini:hover{background:var(--latar)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.metrik{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.metrik .angka-besar{font-size:26px;font-weight:700}
.metrik .label{font-size:11px;color:var(--teks-lemah);margin-top:4px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media (max-width:800px){.grid2{grid-template-columns:1fr}}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.kartu h3{font-size:13px;font-weight:700;margin-bottom:10px}
.baris{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.info{padding:12px;background:var(--emas-muda);border:1px solid #E8D9A0;border-radius:8px;
  font-size:11.5px;color:#6B540A;line-height:1.7}
.kosong{color:var(--teks-lemah);font-size:12px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Analitik &amp; laporan</h2>
    <p>Ringkasan operasional dan strategis LMS (BRD §5.11-5.12)</p>
  </div>
  <div class="tautan">
    <a href="{{ route('lms.admin.analytics.training-report') }}" class="mini">Laporan Pelatihan</a>
    <a href="{{ route('lms.admin.analytics.evaluation-report') }}" class="mini">Laporan Evaluasi</a>
    <a href="{{ route('lms.admin.analytics.competency-report') }}" class="mini">Laporan Kompetensi</a>
    <a href="{{ route('lms.admin.analytics.talent-report') }}" class="mini">Laporan Talenta</a>
    <a href="{{ route('lms.admin.analytics.dashboard.export') }}" class="mini" style="background:var(--hijau);color:#fff;border-color:var(--hijau)">⬇ Ekspor CSV</a>
  </div>
</div>

<div class="grid">
  <div class="metrik">
    <div class="angka-besar">{{ $completionRate !== null ? $completionRate.'%' : '—' }}</div>
    <div class="label">Completion Rate (target BRD ≥90%)</div>
  </div>
  <div class="metrik">
    <div class="angka-besar">{{ $activeLearners }}</div>
    <div class="label">Pembelajar Aktif</div>
  </div>
  <div class="metrik">
    <div class="angka-besar">{{ $totalEnrollments }}</div>
    <div class="label">Pendaftaran Disetujui</div>
  </div>
  <div class="metrik">
    <div class="angka-besar">{{ $assessmentPassRate !== null ? $assessmentPassRate.'%' : '—' }}</div>
    <div class="label">Tingkat Lulus Asesmen ({{ $totalAttempts }} percobaan)</div>
  </div>
</div>

<div class="grid2">
  <div class="kartu">
    <h3>Program terpopuler</h3>
    @forelse ($popularCourses as $p)
      <div class="baris"><span>{{ $p->title }}</span><span>{{ $p->jumlah }} pendaftar</span></div>
    @empty
      <div class="kosong">Belum ada data.</div>
    @endforelse
  </div>
  <div class="kartu">
    <h3>Distribusi kategori</h3>
    @forelse ($categoryDistribution as $c)
      <div class="baris"><span>{{ $c->kategori }}</span><span>{{ $c->jumlah }}</span></div>
    @empty
      <div class="kosong">Belum ada data.</div>
    @endforelse
  </div>
</div>

<div class="grid2">
  <div class="kartu">
    <h3>Talent readiness (distribusi)</h3>
    <div class="baris"><span>Tinggi</span><span>{{ $talentDistribution['tinggi'] }} pegawai</span></div>
    <div class="baris"><span>Sedang</span><span>{{ $talentDistribution['sedang'] }} pegawai</span></div>
    <div class="baris"><span>Rendah</span><span>{{ $talentDistribution['rendah'] }} pegawai</span></div>
    <div class="baris"><span>Belum dihitung (belum ada data)</span><span>{{ $talentDistribution['belum_dihitung'] }} pegawai</span></div>
  </div>
  <div class="kartu">
    <h3>5 kompetensi dengan gap terbesar (organisasi)</h3>
    @forelse ($topGaps as $g)
      <div class="baris"><span>{{ $g['competency_name'] }} — {{ $g['position_name'] }}</span><span>Gap {{ $g['avg_gap'] }}</span></div>
    @empty
      <div class="kosong">Tidak ada gap kompetensi tercatat.</div>
    @endforelse
  </div>
</div>

<div class="kartu">
  <h3>ROI Pelatihan &amp; Predictive Analytics</h3>
  <div class="info">
    Belum tersedia — memerlukan data biaya pelatihan dan model nilai-gaji dari Finance/HC yang
    saat ini TIDAK ADA di sistem ini. Menampilkan angka ROI/prediksi tanpa dasar data akan
    menyesatkan pengambilan keputusan, jadi sengaja tidak ditampilkan sampai sumber data itu
    tersedia.
  </div>
</div>
@endsection
