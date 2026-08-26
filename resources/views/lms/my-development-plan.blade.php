@extends('layouts.app')

@section('judul', 'Rencana Pengembangan Saya')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
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
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.gagal{background:#FBE3E3;color:#9B2C2C}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Rencana pengembangan saya</h2>
  <p>Learning path untuk jabatan Anda — dasar rencana pengembangan individu (IDP)</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Urutan</th><th>Kursus</th><th>Sifat</th><th>Status</th></tr>
    </thead>
    <tbody>
      @forelse ($progress as $p)
        <tr>
          <td class="angka">{{ $p->sequence }}</td>
          <td>{{ $p->course_title }}</td>
          <td><span class="tag {{ $p->is_mandatory ? 'wajib' : '' }}">{{ $p->is_mandatory ? 'Wajib' : 'Opsional' }}</span></td>
          <td>
            @if ($p->status === 'lulus')
              <span class="tag lulus">Lulus</span>
            @elseif ($p->status === 'tidak_lulus')
              <span class="tag gagal">Tidak Lulus</span>
            @elseif ($p->status === 'terdaftar')
              <span class="tag">Terdaftar</span>
            @else
              <a href="{{ route('lms.index') }}" class="tag">Belum Daftar — Lihat Jadwal</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Jabatan Anda belum memiliki learning path.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
