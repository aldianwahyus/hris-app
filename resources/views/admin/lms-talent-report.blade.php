@extends('layouts.app')

@section('judul', 'Laporan Talenta')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px}
.ekspor:hover{background:var(--hijau-tua)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.metrik{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.metrik .angka-besar{font-size:24px;font-weight:700}
.metrik .label{font-size:11px;color:var(--teks-lemah);margin-top:4px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.ok{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.risiko{background:#FBE3E3;color:#9B2C2C}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Laporan talenta</h2>
  <a href="{{ route('lms.admin.analytics.talent-report.export') }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="grid">
  <div class="metrik"><div class="angka-besar">{{ $distribution['tinggi'] }}</div><div class="label">Readiness Tinggi</div></div>
  <div class="metrik"><div class="angka-besar">{{ $distribution['sedang'] }}</div><div class="label">Readiness Sedang</div></div>
  <div class="metrik"><div class="angka-besar">{{ $distribution['rendah'] }}</div><div class="label">Readiness Rendah</div></div>
  <div class="metrik"><div class="angka-besar">{{ $distribution['belum_dihitung'] }}</div><div class="label">Belum Ada Data</div></div>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Posisi Kunci</th><th>Jumlah Kandidat</th><th>Cakupan Suksesi</th></tr>
    </thead>
    <tbody>
      @forelse ($keyPositions as $p)
        <tr>
          <td>{{ $p->position_name }}</td>
          <td class="angka">{{ $p->candidate_count }}</td>
          <td>
            @if ($p->has_ready_now)
              <span class="tag ok">Ada kandidat siap sekarang</span>
            @else
              <span class="tag risiko">Belum ada kandidat siap sekarang</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Belum ada rencana suksesi.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
