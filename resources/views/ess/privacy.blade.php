@extends('layouts.app')

@section('judul', 'Privasi Data Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.kartu h3{font-size:13.5px;font-weight:700;margin-bottom:6px}
.kartu p{font-size:12.5px;color:var(--teks-lemah);margin-bottom:12px}
.utama{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff;
  text-decoration:none;display:inline-block}
.utama:hover{background:var(--hijau-tua)}
textarea{width:100%;max-width:520px;border:1px solid var(--garis);border-radius:7px;padding:9px 11px;
  font-family:inherit;font-size:12.5px;resize:vertical;min-height:70px}
.sub-judul{font-size:13px;font-weight:700;margin:22px 0 10px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending{background:var(--emas-muda);color:#7A5F0B}
.status.reviewed{background:#DCEAFB;color:#1D4E89}
.status.completed{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected{background:var(--merah-muda);color:var(--merah)}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Privasi Data Saya</h2>
  <p>Hak Anda atas data pribadi sesuai UU Perlindungan Data Pribadi</p>
</div>

@if (session('sukses'))
  <div class="kartu" style="border-color:var(--hijau);background:var(--hijau-muda)">{{ session('sukses') }}</div>
@endif
@if (session('gagal'))
  <div class="kartu" style="border-color:var(--merah);background:var(--merah-muda)">{{ session('gagal') }}</div>
@endif

<div class="kartu">
  <h3>Unduh Data Saya</h3>
  <p>Unduh salinan data pribadi Anda yang tersimpan di sistem ini (profil, riwayat cuti, izin, dokumen mandiri, dan tiket bantuan) dalam format JSON.</p>
  <a href="{{ route('privacy.export') }}" class="utama">Unduh Data Saya</a>
</div>

<div class="kartu">
  <h3>Ajukan Penghapusan Data</h3>
  <p>Permintaan akan ditinjau manual oleh HC. Perlu dicatat: sebagian data kepegawaian memiliki kewajiban retensi hukum (pajak, ketenagakerjaan) sehingga tidak semua data bisa dihapus otomatis.</p>
  <form method="POST" action="{{ route('privacy.request-deletion') }}">
    @csrf
    <textarea name="reason" placeholder="Alasan permintaan penghapusan data..." required></textarea>
    <div style="margin-top:10px">
      <button type="submit" class="utama">Ajukan Penghapusan</button>
    </div>
  </form>
</div>

<div class="sub-judul">Riwayat Permintaan Penghapusan</div>
<div class="gulir">
  <table>
    <thead>
      <tr><th>Diajukan</th><th>Alasan</th><th>Status</th><th>Catatan</th></tr>
    </thead>
    <tbody>
      @forelse ($requests as $r)
        <tr>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td>{{ $r->reason }}</td>
          <td><span class="status {{ $r->status }}">{{ ['pending' => 'Menunggu', 'reviewed' => 'Ditinjau', 'rejected' => 'Ditolak', 'completed' => 'Selesai'][$r->status] ?? $r->status }}</span></td>
          <td>{{ $r->notes ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="kosong">Belum ada permintaan penghapusan data.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
