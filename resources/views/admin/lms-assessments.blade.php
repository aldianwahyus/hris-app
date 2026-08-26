@extends('layouts.app')

@section('judul', 'Assessment Center')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil select{padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Assessment center</h2>
  <p>Ujian online — bank soal, scoring otomatis (pilihan ganda), penilaian manual (esai)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.assessments.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Judul</label>
      <input type="text" name="title" required maxlength="200">
    </div>
    <div class="bidang-kecil" style="min-width:160px">
      <label>Kursus Terkait (opsional)</label>
      <select name="course_id">
        <option value="">— Tidak terikat —</option>
        @foreach ($courses as $c)
          <option value="{{ $c->id }}">{{ $c->title }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Nilai Lulus</label>
      <input type="number" name="passing_score" required value="70" min="0" max="100" step="0.01" style="width:90px">
    </div>
    <div class="bidang-kecil">
      <label>Durasi (menit)</label>
      <input type="number" name="duration_minutes" min="1" style="width:100px">
    </div>
    <button type="submit" class="mini utama">Tambah Asesmen</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Kursus</th><th>Nilai Lulus</th><th>Soal</th><th>Percobaan</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($assessments as $a)
        <tr>
          <td>{{ $a->title }}</td>
          <td>{{ $a->course_title ?? '—' }}</td>
          <td class="angka">{{ $a->passing_score }}</td>
          <td class="angka">{{ $questionCounts[$a->id] ?? 0 }}</td>
          <td class="angka">{{ $attemptCounts[$a->id] ?? 0 }}</td>
          <td>{{ $a->is_active ? 'Aktif' : 'Nonaktif' }}</td>
          <td>
            <a href="{{ route('lms.admin.assessments.questions', $a->id) }}" class="mini">Soal</a>
            <a href="{{ route('lms.admin.assessments.attempts', $a->id) }}" class="mini">Hasil</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="kosong">Belum ada asesmen.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
