@extends('layouts.app')

@section('judul', 'Forum Diskusi')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-end}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff;text-decoration:none}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:var(--latar)}
tbody a{color:inherit}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Forum diskusi</h2>
    <p>Knowledge sharing &amp; community learning (BRD §5.9)</p>
  </div>
  <a href="{{ route('lms.forum.create') }}" class="mini">+ Buat Diskusi</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Kursus</th><th>Oleh</th><th>Balasan</th><th>Dibuat</th></tr>
    </thead>
    <tbody>
      @forelse ($threads as $t)
        <tr>
          <td>
            <a href="{{ route('lms.forum.show', $t->id) }}">
              @if ($t->is_pinned)<span class="tag">Disematkan</span>@endif
              {{ $t->title }}
            </a>
          </td>
          <td>{{ $t->course_title ?? '—' }}</td>
          <td>{{ $t->full_name }}</td>
          <td class="angka">{{ $t->reply_count }}</td>
          <td class="angka">{{ date('j M Y', strtotime($t->created_at)) }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada diskusi.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
