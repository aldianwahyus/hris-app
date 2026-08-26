@extends('layouts.app')

@section('judul', 'Evaluasi Pelatihan')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:800px){.grid{grid-template-columns:1fr}}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.kartu h3{font-size:13px;font-weight:700;margin-bottom:10px}
.bidang{margin-bottom:12px}
.bidang label{display:block;font-size:11px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px}
.bidang select,.bidang textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.info{padding:9px 12px;background:var(--emas-muda);border:1px solid #E8D9A0;border-radius:8px;
  font-size:11px;color:#6B540A;margin-bottom:12px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.baris{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.kosong{color:var(--teks-lemah);font-size:12px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $enrollment->full_name }} — {{ $enrollment->course_title }}</h2>
  <p>{{ $enrollment->nrp }}</p>
</div>

<div class="grid">
  <div class="kartu">
    <h3>Level 1 — Kepuasan peserta (diisi pegawai)</h3>
    @if ($evaluation && $evaluation->satisfaction_score !== null)
      <div class="baris"><span>Skor</span><span>{{ $evaluation->satisfaction_score }}/5</span></div>
      <div class="baris"><span>Komentar</span><span>{{ $evaluation->satisfaction_comments ?? '—' }}</span></div>
    @else
      <div class="kosong">Pegawai belum mengisi evaluasi kepuasan.</div>
    @endif
  </div>

  <div class="kartu">
    <h3>Level 2 — Peningkatan knowledge</h3>
    <div class="kosong">Lihat <a href="{{ route('lms.admin.evaluations.pre-post-report') }}">Laporan Pre/Post Test</a> (pakai ulang Assessment Center).</div>
  </div>
</div>

<div class="kartu">
  <h3>Level 3 — Perubahan perilaku kerja &amp; Level 4 — Dampak (diisi HC)</h3>
  <div class="info">
    Level 4 SENGAJA kualitatif (catatan bebas) — HRIS ini tidak punya sistem KPI/penilaian
    kinerja terukur, mengarang angka KPI akan jadi data palsu.
  </div>
  <form method="POST" action="{{ route('lms.admin.evaluations.update', $enrollment->id) }}">
    @csrf
    <div class="bidang">
      <label>Skor Perubahan Perilaku (Level 3, 1-5)</label>
      <select name="behavior_score">
        <option value="">— Belum dinilai —</option>
        @foreach ([1, 2, 3, 4, 5] as $s)
          <option value="{{ $s }}" @selected(optional($evaluation)->behavior_score == $s)>{{ $s }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang">
      <label>Catatan Perilaku</label>
      <textarea name="behavior_comments" rows="3">{{ optional($evaluation)->behavior_comments }}</textarea>
    </div>
    <div class="bidang">
      <label>Catatan Dampak Pasca-Pelatihan (Level 4, kualitatif)</label>
      <textarea name="impact_notes" rows="3">{{ optional($evaluation)->impact_notes }}</textarea>
    </div>
    <button type="submit" class="mini">Simpan Evaluasi</button>
  </form>
</div>
@endsection
