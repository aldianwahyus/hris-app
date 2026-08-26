@extends('layouts.app')

@section('judul', 'Kompetensi Pegawai')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);margin-bottom:16px}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody select{padding:6px 8px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:12px}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.gap{background:#FBE3E3;color:#9B2C2C}
.tag.ok{background:var(--hijau-muda);color:var(--hijau-tua)}
.aksi{margin-bottom:24px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:10px}
.rekom{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.rekom:last-child{border-bottom:0}
.kosong{padding:20px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $employee->full_name }}<br><small style="font-weight:400;font-size:12px;color:var(--teks-lemah)">{{ $employee->nrp }} · {{ $employee->position_name }}</small></h2>
</div>

@if ($rows->isEmpty())
  <div class="gulir"><div class="kosong">Jabatan ini belum punya peta kompetensi wajib — atur lewat Daftar Jabatan.</div></div>
@else
  <form method="POST" action="{{ route('lms.admin.employee-competency.update', $employee->id) }}">
    @csrf
    <div class="gulir">
      <table>
        <thead>
          <tr><th>Kompetensi</th><th>Wajib</th><th>Level Saat Ini</th><th>Gap</th></tr>
        </thead>
        <tbody>
          @foreach ($rows as $r)
            <tr>
              <td>{{ $r->name }}<br><small style="color:var(--teks-lemah)">{{ $r->category }}</small></td>
              <td class="angka">{{ $r->required_level }}</td>
              <td>
                <select name="current_level[{{ $r->id }}]">
                  @foreach ([0, 1, 2, 3, 4, 5] as $lvl)
                    <option value="{{ $lvl }}" @selected($r->current_level == $lvl)>{{ $lvl }}</option>
                  @endforeach
                </select>
              </td>
              <td>
                @if ($r->gap > 0)
                  <span class="tag gap">Gap {{ $r->gap }}</span>
                @else
                  <span class="tag ok">Terpenuhi</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="aksi">
      <button type="submit" class="mini">Simpan Penilaian</button>
    </div>
  </form>
@endif

<div class="kartu">
  <h3>Rekomendasi kursus (berbasis gap kompetensi)</h3>
  @forelse ($recommendations as $rec)
    <div class="rekom">
      <span>{{ $rec->title }}</span>
      <span style="color:var(--teks-lemah)">Menutup {{ $rec->gap_covered }} kompetensi bergap</span>
    </div>
  @empty
    <div class="kosong">Tidak ada rekomendasi — tidak ada gap kompetensi, atau belum ada kursus yang ditandai menutup kompetensi yang bergap.</div>
  @endforelse
</div>
@endsection
