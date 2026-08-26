@extends('layouts.app')

@section('judul', 'Kalender Hari Libur Nasional')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil select{padding:8px 10px;border:1px solid var(--garis);border-radius:7px;
  font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Kalender hari libur nasional</h2>
  <p>Dipakai untuk mengecualikan akhir pekan & hari libur dari hitungan hari cuti dan hari kerja absensi</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.holidays.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Tanggal</label>
      <input type="date" name="holiday_date" required value="{{ old('holiday_date') }}">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:200px">
      <label>Nama hari libur</label>
      <input type="text" name="name" required maxlength="150" value="{{ old('name') }}" placeholder="mis. Hari Kemerdekaan RI">
    </div>
    <div class="bidang-kecil">
      <label>Jenis</label>
      <select name="is_national">
        <option value="1">Libur nasional</option>
        <option value="0">Cuti bersama</option>
      </select>
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:160px">
      <label>Rujukan (opsional)</label>
      <input type="text" name="source_document" maxlength="150" value="{{ old('source_document') }}" placeholder="mis. SKB 3 Menteri 2026">
    </div>
    <button type="submit" class="mini utama">Tambah</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Tanggal</th><th>Nama</th><th>Jenis</th><th>Rujukan</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($holidays as $h)
        <tr>
          <td class="angka">{{ date('j M Y', strtotime($h->holiday_date)) }}</td>
          <td>{{ $h->name }}</td>
          <td><span class="tag">{{ $h->is_national ? 'Nasional' : 'Cuti bersama' }}</span></td>
          <td>{{ $h->source_document ?? '—' }}</td>
          <td>
            <form method="POST" action="{{ route('sysadmin.holidays.destroy', $h->id) }}"
                  data-confirm="Hapus hari libur {{ $h->name }}?">
              @csrf
              @method('DELETE')
              <button type="submit" class="mini">Hapus</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada hari libur yang ditambahkan.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
