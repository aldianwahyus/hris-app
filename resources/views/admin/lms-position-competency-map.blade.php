@extends('layouts.app')

@section('judul', 'Peta Kompetensi Jabatan')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody select{padding:6px 8px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:12px}
.aksi{margin-top:16px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Peta kompetensi — {{ $position->name }}</h2>
  <p>Level kompetensi yang wajib dimiliki pemegang jabatan ini (0 = tidak wajib)</p>
</div>

@if ($competencies->isEmpty())
  <div class="gulir"><div class="kosong">Belum ada kompetensi aktif — tambahkan lewat halaman Kompetensi.</div></div>
@else
  <form method="POST" action="{{ route('lms.admin.competencies.map-position.store', $position->id) }}">
    @csrf
    <div class="gulir">
      <table>
        <thead>
          <tr><th>Kompetensi</th><th>Kategori</th><th>Level Wajib</th></tr>
        </thead>
        <tbody>
          @foreach ($competencies as $c)
            <tr>
              <td>{{ $c->name }}</td>
              <td>{{ $c->category ?? '—' }}</td>
              <td>
                <select name="required_level[{{ $c->id }}]">
                  @foreach ([0, 1, 2, 3, 4, 5] as $lvl)
                    <option value="{{ $lvl }}" @selected(($requiredLevels[$c->id] ?? 0) == $lvl)>{{ $lvl === 0 ? 'Tidak wajib' : $lvl }}</option>
                  @endforeach
                </select>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="aksi">
      <button type="submit" class="mini">Simpan Peta Kompetensi</button>
    </div>
  </form>
@endif
@endsection
