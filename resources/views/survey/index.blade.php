@extends('layouts.app')

@section('judul', 'Survei Keterlibatan')
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.daftar{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
.baris{padding:14px 16px;border-bottom:1px solid var(--garis);font-size:12.5px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.baris:last-child{border-bottom:0}
.j{font-weight:600;font-size:12.5px}
.s{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Survei Keterlibatan</h2>
  <p>Survei aktif yang menyasar Anda saat ini</p>
</div>

<div class="daftar">
  @forelse ($surveys as $s)
    <div class="baris">
      <div>
        <div class="j">{{ $s->title }}</div>
        <div class="s">Berlaku sampai {{ date('j M Y', strtotime($s->end_date)) }}{{ $s->is_anonymous ? ' · Anonim' : '' }}</div>
      </div>
      @if (in_array($s->id, $respondedIds, true))
        <span class="status selesai">Sudah Diisi</span>
      @else
        <a href="{{ route('survey.fill', $s->id) }}" class="btn" style="padding:7px 14px;font-size:12px">Isi Survei</a>
      @endif
    </div>
  @empty
    <div class="kosong">Tidak ada survei yang aktif saat ini.</div>
  @endforelse
</div>
@endsection
