@extends('layouts.app')

@section('judul', 'Pengaduan')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:6px}
.kartu p.ket{font-size:12.5px;color:var(--teks-lemah);margin-bottom:12px}
label.field{display:block;font-size:11.5px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px}
select,textarea{width:100%;max-width:520px;border:1px solid var(--garis);border-radius:7px;padding:9px 11px;
  font-family:inherit;font-size:12.5px}
textarea{resize:vertical;min-height:100px}
.grup-input{margin-bottom:14px}
.anonim{display:flex;align-items:flex-start;gap:8px;background:var(--latar);border-radius:8px;
  padding:11px 13px;margin-bottom:14px;max-width:520px}
.anonim input{margin-top:2px}
.anonim span{font-size:11.5px;color:var(--teks-lemah);line-height:1.5}
.utama{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.utama:hover{background:var(--hijau-tua)}
.sub-judul{font-size:13px;font-weight:700;margin:22px 0 10px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;border-bottom:1px solid var(--garis)}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.baru{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pengaduan</h2>
  <p>Laporkan dugaan pelanggaran — dapat dikirim secara anonim</p>
</div>

@if (session('sukses'))
  <div class="kartu" style="border-color:var(--hijau);background:var(--hijau-muda)">{{ session('sukses') }}</div>
@endif
@if (session('gagal'))
  <div class="kartu" style="border-color:var(--merah);background:var(--merah-muda)">{{ session('gagal') }}</div>
@endif

<div class="kartu">
  <h3>Buat Laporan</h3>
  <p class="ket">Laporan diterima langsung oleh hr_approver — TIDAK dapat dilihat hr_admin kantor Anda.</p>
  <form method="POST" action="{{ route('whistleblowing.store') }}">
    @csrf
    <div class="grup-input">
      <label class="field">Kategori</label>
      <select name="category" required>
        <option value="">Pilih kategori...</option>
        @foreach ($categories as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="grup-input">
      <label class="field">Uraian</label>
      <textarea name="description" placeholder="Jelaskan kronologi, pihak yang terlibat, dan bukti pendukung bila ada..." required></textarea>
    </div>
    <label class="anonim">
      <input type="checkbox" name="is_anonymous" value="1">
      <span>Laporkan secara anonim. Identitas Anda TIDAK akan disimpan sama sekali — namun karena itu,
        laporan ini juga TIDAK akan muncul di riwayat Anda dan Anda tidak dapat memeriksa statusnya nanti.</span>
    </label>
    <button type="submit" class="utama">Kirim Laporan</button>
  </form>
</div>

<div class="sub-judul">Riwayat Laporan Saya</div>
<p style="font-size:11.5px;color:var(--teks-lemah);margin:-6px 0 10px">
  Hanya menampilkan laporan yang dikirim TIDAK secara anonim.
</p>
<div class="gulir">
  <table>
    <thead>
      <tr><th>Diajukan</th><th>Kategori</th><th>Status</th></tr>
    </thead>
    <tbody>
      @forelse ($reports as $r)
        <tr>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td>{{ $categories[$r->category] ?? $r->category }}</td>
          <td><span class="status {{ $r->status }}">{{ ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai'][$r->status] ?? $r->status }}</span></td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="kosong">Belum ada laporan non-anonim.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
