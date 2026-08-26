@extends('layouts.app')

@section('judul', 'Detail Learning Path')
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
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.wajib{background:#FBE3E3;color:#9B2C2C}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $path->title }}</h2>
  <p>{{ $path->position_name }}{{ $path->description ? ' · '.$path->description : '' }}</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.learning-paths.courses.store', $path->id) }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="min-width:220px">
      <label>Kursus</label>
      <select name="course_id" required>
        <option value="">— Pilih —</option>
        @foreach ($availableCourses as $c)
          <option value="{{ $c->id }}">{{ $c->title }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Urutan</label>
      <input type="number" name="sequence" required min="1" style="width:80px">
    </div>
    <div class="bidang-kecil" style="flex-direction:row;align-items:center;gap:6px;padding-bottom:8px">
      <input type="checkbox" name="is_mandatory" value="1" id="wajib" checked>
      <label for="wajib" style="font-weight:500">Wajib</label>
    </div>
    <button type="submit" class="mini utama">Tambah</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Urutan</th><th>Kursus</th><th>Sifat</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($pathCourses as $pc)
        <tr>
          <td class="angka">{{ $pc->sequence }}</td>
          <td>{{ $pc->course_title }}</td>
          <td><span class="tag {{ $pc->is_mandatory ? 'wajib' : '' }}">{{ $pc->is_mandatory ? 'Wajib' : 'Opsional' }}</span></td>
          <td>
            <form method="POST" action="{{ route('lms.admin.learning-paths.courses.destroy', [$path->id, $pc->id]) }}"
                  data-confirm="Hapus kursus ini dari learning path?">
              @csrf @method('DELETE')
              <button type="submit" class="mini">Hapus</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada kursus di learning path ini.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
