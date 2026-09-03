@extends('layouts.app')

@section('judul', 'Tiket '.$ticket->ticket_number)
@section('peran', 'Employee Self Service')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.terbuka{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditutup{background:#EDEDED;color:#6B6B6B}
.thread{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:4px 0;margin-bottom:16px}
.pesan-baris{padding:12px 16px;border-bottom:1px solid var(--garis)}
.pesan-baris:last-child{border-bottom:0}
.pesan-atas{display:flex;justify-content:space-between;gap:10px;font-size:11px;color:var(--teks-lemah);margin-bottom:4px}
.pesan-nama{font-weight:700;color:var(--teks)}
.pesan-isi{font-size:12.5px;line-height:1.6;white-space:pre-line}
.aksi{margin-top:4px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $ticket->subject }}</h2>
    <p>{{ $ticket->ticket_number }} &middot; {{ $categories[$ticket->category] ?? $ticket->category }}</p>
  </div>
  <span class="status {{ $ticket->status }}">
    {{ ['terbuka' => 'Terbuka', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'][$ticket->status] ?? $ticket->status }}
  </span>
</div>

<div class="thread">
  <div class="pesan-baris">
    <div class="pesan-atas"><span class="pesan-nama">Anda</span><span>{{ date('j M Y, H:i', strtotime($ticket->created_at)) }}</span></div>
    <div class="pesan-isi">{{ $ticket->description }}</div>
  </div>
  @foreach ($replies as $r)
    <div class="pesan-baris">
      <div class="pesan-atas"><span class="pesan-nama">{{ $r->author_name }}</span><span>{{ date('j M Y, H:i', strtotime($r->created_at)) }}</span></div>
      <div class="pesan-isi">{{ $r->message }}</div>
    </div>
  @endforeach
</div>

@if ($ticket->status !== 'ditutup')
  <div class="kartu" style="max-width:560px">
    <div class="kartu-judul">Balas</div>
    <form method="POST" action="{{ route('helpdesk.reply', $ticket->id) }}">
      @csrf
      <div class="bidang">
        <textarea name="message" rows="3" maxlength="2000" required placeholder="Tulis balasan..."></textarea>
      </div>
      <div class="aksi">
        <button type="submit" class="btn">Kirim Balasan</button>
      </div>
    </form>
  </div>
@endif
@endsection
