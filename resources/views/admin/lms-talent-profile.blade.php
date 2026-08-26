@extends('layouts.app')

@section('judul', 'Profil Talenta')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media (max-width:800px){.grid{grid-template-columns:1fr}}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:10px}
.bidang{margin-bottom:10px}
.bidang label{display:block;font-size:11px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px}
.bidang input,.bidang select,.bidang textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.info{padding:9px 12px;background:var(--emas-muda);border:1px solid #E8D9A0;border-radius:8px;
  font-size:11px;color:#6B540A;margin-bottom:12px}
.skor{font-size:32px;font-weight:700;text-align:center;margin:8px 0}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.baris{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.kosong{color:var(--teks-lemah);font-size:12px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $employee->full_name }}</h2>
  <p>{{ $employee->nrp }} · {{ $employee->position_name }}</p>
</div>

<div class="grid">
  <div class="kartu">
    <h3>Penilaian talenta</h3>
    <div class="info">Performance &amp; potential diisi manual HC (proksi) — HRIS ini belum punya sistem penilaian kinerja historis.</div>
    <form method="POST" action="{{ route('lms.admin.talent.update', $employee->id) }}">
      @csrf
      <div class="bidang">
        <label>Performance Score (1-5)</label>
        <select name="performance_score" required>
          @foreach ([1, 2, 3, 4, 5] as $s)
            <option value="{{ $s }}" @selected(optional($profile)->performance_score == $s)>{{ $s }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label>Potential Score (1-5)</label>
        <select name="potential_score" required>
          @foreach ([1, 2, 3, 4, 5] as $s)
            <option value="{{ $s }}" @selected(optional($profile)->potential_score == $s)>{{ $s }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang">
        <label>Catatan</label>
        <textarea name="notes" rows="3">{{ optional($profile)->notes }}</textarea>
      </div>
      <button type="submit" class="mini">Simpan</button>
    </form>
  </div>

  <div class="kartu">
    <h3>Readiness score (dihitung sistem)</h3>
    <div class="skor">{{ $readinessScore !== null ? number_format($readinessScore * 100, 0).'%' : '—' }}</div>
    <p style="font-size:11px;color:var(--teks-lemah);text-align:center">Gabungan capaian kompetensi (40%), penyelesaian learning path (30%), potential score (30%) — hanya komponen yang tersedia datanya yang dihitung.</p>
  </div>
</div>

<div class="grid">
  <div class="kartu">
    <h3>Progres learning path</h3>
    @forelse ($pathProgress as $p)
      <div class="baris">
        <span>{{ $p->course_title }}</span>
        <span class="tag {{ $p->status === 'lulus' ? 'lulus' : '' }}">{{ $p->status }}</span>
      </div>
    @empty
      <div class="kosong">Jabatan ini belum punya learning path.</div>
    @endforelse
  </div>

  <div class="kartu">
    <h3>Rekomendasi kursus (gap kompetensi)</h3>
    @forelse ($recommendations as $rec)
      <div class="baris">
        <span>{{ $rec->title }}</span>
        <span style="color:var(--teks-lemah)">{{ $rec->gap_covered }} kompetensi</span>
      </div>
    @empty
      <div class="kosong">Tidak ada rekomendasi saat ini.</div>
    @endforelse
  </div>
</div>
@endsection
