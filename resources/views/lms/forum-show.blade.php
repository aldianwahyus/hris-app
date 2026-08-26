@extends('layouts.app')

@section('judul', $thread->title)
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:12px}
.kartu .meta{font-size:11px;color:var(--teks-lemah);margin-bottom:8px}
.kartu p{font-size:13px;line-height:1.6;white-space:pre-wrap}
.aksi{margin-top:10px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.balas{margin-top:16px}
.balas textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.utama{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff;margin-top:8px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $thread->title }}</h2>
  <p>{{ $thread->course_title ?? 'Diskusi umum' }}</p>
</div>

<div class="kartu">
  <div class="meta">{{ $thread->full_name }} · {{ date('j M Y H:i', strtotime($thread->created_at)) }}</div>
  <p>{{ $thread->body }}</p>
  @can('lms-catalog.manage')
    <div class="aksi">
      <form method="POST" action="{{ route('lms.admin.forum.threads.destroy', $thread->id) }}" data-confirm="Hapus diskusi ini beserta seluruh balasannya?">
        @csrf @method('DELETE')
        <button type="submit" class="mini">Hapus Diskusi</button>
      </form>
    </div>
  @endcan
</div>

@foreach ($replies as $r)
  <div class="kartu">
    <div class="meta">{{ $r->full_name }} · {{ date('j M Y H:i', strtotime($r->created_at)) }}</div>
    <p>{{ $r->body }}</p>
    @can('lms-catalog.manage')
      <div class="aksi">
        <form method="POST" action="{{ route('lms.admin.forum.replies.destroy', [$thread->id, $r->id]) }}" data-confirm="Hapus balasan ini?">
          @csrf @method('DELETE')
          <button type="submit" class="mini">Hapus Balasan</button>
        </form>
      </div>
    @endcan
  </div>
@endforeach

<div class="balas">
  <form method="POST" action="{{ route('lms.forum.reply', $thread->id) }}">
    @csrf
    <textarea name="body" required rows="3" placeholder="Tulis balasan..."></textarea>
    <button type="submit" class="utama">Kirim Balasan</button>
  </form>
</div>
@endsection
