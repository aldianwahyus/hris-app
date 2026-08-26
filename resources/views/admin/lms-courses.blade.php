@extends('layouts.app')

@section('judul', 'Kelola Pelatihan')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:20px;display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}
.kepala h2{font-size:19px;font-weight:800;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:4px}

.statistik{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.stat{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:14px 16px;
  display:flex;align-items:center;gap:12px}
.stat .ikon{width:38px;height:38px;border-radius:10px;background:var(--hijau-muda);color:var(--hijau-tua);
  display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stat .nilai{font-size:20px;font-weight:800;letter-spacing:-.02em;line-height:1.1}
.stat .label{font-size:11px;color:var(--teks-lemah);margin-top:2px}

.subnav{display:flex;gap:6px;overflow-x:auto;padding-bottom:10px;margin-bottom:20px;
  border-bottom:1px solid var(--garis)}
.subnav::-webkit-scrollbar{height:5px}
.subnav::-webkit-scrollbar-thumb{background:var(--garis);border-radius:99px}
.subnav a{flex-shrink:0;padding:7px 13px;border-radius:99px;font-size:12px;font-weight:600;
  color:var(--teks-lemah);text-decoration:none;background:var(--latar);border:1px solid transparent;
  transition:.15s}
.subnav a:hover{background:var(--hijau-muda);color:var(--hijau-tua);border-color:#CFE7DD}

.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:18px;margin-bottom:18px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
  color:var(--teks-lemah);margin-bottom:14px;display:flex;align-items:center;gap:7px}
.kartu-judul .titik{width:6px;height:6px;border-radius:99px;background:var(--hijau)}

.grid-form{display:grid;grid-template-columns:120px 1fr 160px 1.4fr auto;gap:12px;align-items:end}
@media (max-width:900px){.grid-form{grid-template-columns:1fr 1fr}}
.bidang-kecil{display:flex;flex-direction:column;gap:6px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil textarea,.bidang-kecil select{padding:9px 11px;border:1.5px solid var(--garis);
  border-radius:8px;font-family:inherit;font-size:12.5px;background:var(--latar);transition:.15s}
.bidang-kecil input:focus,.bidang-kecil textarea:focus,.bidang-kecil select:focus{
  border-color:var(--hijau);background:var(--putih);outline:none}

.daftar-kursus{display:flex;flex-direction:column;gap:14px}
.kursus{border:1px solid var(--garis);border-left:3px solid var(--hijau);border-radius:var(--r);
  overflow:hidden;background:var(--putih);transition:box-shadow .15s}
.kursus:hover{box-shadow:0 4px 16px rgba(15,31,26,.06)}
.kursus.nonaktif{border-left-color:var(--garis)}
.kursus-judul{display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:15px 18px;background:#FAFAF8;flex-wrap:wrap}
.kursus-judul h3{font-size:14.5px;font-weight:700}
.kursus-judul .kode{display:inline-block;font-size:10.5px;font-weight:700;color:var(--teks-lemah);
  background:var(--latar);border:1px solid var(--garis);border-radius:5px;padding:1px 6px;margin-right:6px}
.kursus-judul small{display:block;color:var(--teks-lemah);font-size:11.5px;margin-top:4px;font-weight:400}
.kursus-aksi{display:flex;gap:6px;align-items:center;flex-shrink:0}

.gulir{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:10px 14px;
  border-bottom:1px solid var(--garis);white-space:nowrap;background:var(--putih)}
tbody td{padding:11px 14px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover td{background:#FAFDFB}

.tag{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 9px;
  border-radius:99px;background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag .titik{width:5px;height:5px;border-radius:99px;background:currentColor}
.tag.aktif{background:var(--hijau-muda);color:var(--hijau-tua);border-color:#CFE7DD}

.mini{padding:6px 13px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks);display:inline-flex;align-items:center;transition:.12s}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}

.kosong{padding:32px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
.kosong-utama{padding:40px;text-align:center;color:var(--teks-lemah);font-size:13px;
  background:var(--putih);border:1.5px dashed var(--garis);border-radius:var(--r)}

.lipat-jadwal{border-top:1px solid var(--garis)}
.lipat-jadwal summary{list-style:none;cursor:pointer;padding:11px 18px;font-size:12px;font-weight:600;
  color:var(--hijau);display:flex;align-items:center;gap:6px;user-select:none}
.lipat-jadwal summary::-webkit-details-marker{display:none}
.lipat-jadwal summary::before{content:'+';font-size:14px;font-weight:800;transition:transform .15s}
.lipat-jadwal[open] summary::before{content:'−'}
.lipat-jadwal summary:hover{background:var(--latar)}
.tambah-jadwal{padding:14px 18px 16px;background:#FAFAF8;border-top:1px solid var(--garis)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Kelola Pelatihan</h2>
    <p>Katalog kursus, jadwal kelas, dan pencatatan kelulusan (LMS)</p>
  </div>
</div>

@php
  $totalJadwal = collect($batches)->sum(fn ($b) => count($b));
  $totalPeserta = collect($enrolledCounts ?? [])->sum();
@endphp
<div class="statistik">
  <div class="stat">
    <div class="ikon">📚</div>
    <div><div class="nilai">{{ count($courses) }}</div><div class="label">Kursus Tersedia</div></div>
  </div>
  <div class="stat">
    <div class="ikon">🗓</div>
    <div><div class="nilai">{{ $totalJadwal }}</div><div class="label">Jadwal Kelas</div></div>
  </div>
  <div class="stat">
    <div class="ikon">👥</div>
    <div><div class="nilai">{{ $totalPeserta }}</div><div class="label">Peserta Terdaftar</div></div>
  </div>
</div>

<div class="subnav">
  <a href="{{ route('lms.admin.competencies.index') }}">Kompetensi</a>
  <a href="{{ route('lms.admin.learning-paths.index') }}">Learning Path</a>
  <a href="{{ route('lms.admin.assessments.index') }}">Assessment Center</a>
  <a href="{{ route('lms.admin.talent.index') }}">Talent Management</a>
  <a href="{{ route('lms.admin.succession.index') }}">Succession Planning</a>
  <a href="{{ route('lms.admin.badges.index') }}">Gamifikasi</a>
  <a href="{{ route('lms.admin.challenges.index') }}">Challenge</a>
  <a href="{{ route('lms.admin.leaderboard.index') }}">Papan Peringkat</a>
  <a href="{{ route('lms.admin.analytics.dashboard') }}">Analitik &amp; Laporan</a>
  <a href="{{ route('lms.forum.index') }}">Forum</a>
  <a href="{{ route('lms.admin.live-sessions.index') }}">Sesi Live/Mentoring</a>
  <a href="{{ route('lms.admin.evaluations.pre-post-report') }}">Laporan Pre/Post Test</a>
  <a href="{{ route('lms.admin.library.index') }}">Kelola Perpustakaan Digital</a>
</div>

<div class="kartu">
  <div class="kartu-judul"><span class="titik"></span> Tambah Kursus Baru</div>
  <form method="POST" action="{{ route('lms.admin.courses.store') }}" class="grid-form">
    @csrf
    <div class="bidang-kecil">
      <label>Kode Kursus</label>
      <input type="text" name="code" required maxlength="30" placeholder="mis. K3-01">
    </div>
    <div class="bidang-kecil">
      <label>Judul</label>
      <input type="text" name="title" required maxlength="200" placeholder="mis. Keselamatan Kerja Dasar">
    </div>
    <div class="bidang-kecil">
      <label>Kategori</label>
      <input type="text" name="category" maxlength="100" placeholder="mis. Wajib">
    </div>
    <div class="bidang-kecil">
      <label>Deskripsi</label>
      <input type="text" name="description" placeholder="Ringkasan kursus (opsional)">
    </div>
    <button type="submit" class="mini utama">+ Tambah Kursus</button>
  </form>
</div>

<div class="daftar-kursus">
  @forelse ($courses as $c)
    <div class="kursus {{ $c->is_active ? '' : 'nonaktif' }}">
      <div class="kursus-judul">
        <div>
          <h3><span class="kode">{{ $c->code }}</span>{{ $c->title }}</h3>
          <small>{{ $c->category ?? 'Tanpa kategori' }} · {{ count($batches[$c->id] ?? []) }} jadwal kelas</small>
        </div>
        <div class="kursus-aksi">
          <span class="tag {{ $c->is_active ? 'aktif' : '' }}"><span class="titik"></span>{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</span>
          <a href="{{ route('lms.admin.competencies.map-course', $c->id) }}" class="mini">Kompetensi</a>
          <form method="POST" action="{{ route('lms.admin.courses.destroy', $c->id) }}"
                data-confirm="Hapus kursus ini? Hanya bisa jika belum punya jadwal kelas.">
            @csrf @method('DELETE')
            <button type="submit" class="mini">Hapus</button>
          </form>
        </div>
      </div>

      <div class="gulir">
        <table>
          <thead>
            <tr><th>Kode Jadwal</th><th>Tanggal</th><th>Lokasi</th><th>Instruktur</th><th>Kuota</th><th>Status</th><th>Peserta</th><th>Sesi &amp; Absensi</th></tr>
          </thead>
          <tbody>
            @forelse ($batches[$c->id] ?? [] as $b)
              <tr>
                <td class="angka">{{ $b->batch_code }}</td>
                <td class="angka">{{ date('j M Y', strtotime($b->start_date)) }} – {{ date('j M Y', strtotime($b->end_date)) }}</td>
                <td>{{ $b->location ?? '—' }}</td>
                <td>{{ $b->instructor_name ?? '—' }}</td>
                <td class="angka">{{ ($enrolledCounts[$b->id] ?? 0) }}{{ $b->capacity ? '/'.$b->capacity : '' }}</td>
                <td><span class="tag"><span class="titik"></span>{{ $b->status }}</span></td>
                <td><a href="{{ route('lms.admin.batches.roster', $b->id) }}" class="mini">Peserta</a></td>
                <td><a href="{{ route('lms.admin.batches.sessions', $b->id) }}" class="mini">Sesi</a></td>
              </tr>
            @empty
              <tr><td colspan="8" class="kosong">Belum ada jadwal kelas untuk kursus ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <details class="lipat-jadwal">
        <summary>Tambah Jadwal Kelas</summary>
        <div class="tambah-jadwal">
          <form method="POST" action="{{ route('lms.admin.batches.store', $c->id) }}" class="baris-tambah" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            @csrf
            <div class="bidang-kecil">
              <label>Kode Jadwal</label>
              <input type="text" name="batch_code" required maxlength="30" placeholder="mis. BATCH-1" style="width:110px">
            </div>
            <div class="bidang-kecil">
              <label>Mulai</label>
              <input type="date" name="start_date" required>
            </div>
            <div class="bidang-kecil">
              <label>Selesai</label>
              <input type="date" name="end_date" required>
            </div>
            <div class="bidang-kecil">
              <label>Batas Daftar</label>
              <input type="date" name="registration_deadline">
            </div>
            <div class="bidang-kecil" style="width:100px">
              <label>Kuota</label>
              <input type="number" name="capacity" min="1" placeholder="tanpa batas">
            </div>
            <div class="bidang-kecil" style="min-width:140px">
              <label>Lokasi</label>
              <input type="text" name="location" maxlength="200">
            </div>
            <div class="bidang-kecil" style="min-width:140px">
              <label>Instruktur</label>
              <input type="text" name="instructor_name" maxlength="150">
            </div>
            <button type="submit" class="mini utama">Tambah Jadwal</button>
          </form>
        </div>
      </details>
    </div>
  @empty
    <div class="kosong-utama">Belum ada kursus. Tambahkan lewat form di atas untuk mulai membangun katalog pelatihan.</div>
  @endforelse
</div>
@endsection
