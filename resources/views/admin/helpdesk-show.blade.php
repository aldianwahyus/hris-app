@extends('layouts.app')

@section('judul', 'Tiket '.$ticket->ticket_number)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.terbuka{background:var(--emas-muda);color:#7A5F0B}
.status.diproses{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditutup{background:#EDEDED;color:#6B6B6B}
.panel{display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start}
@media (max-width: 860px){.panel{grid-template-columns:1fr}}
.thread{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:4px 0;margin-bottom:16px}
.pesan-baris{padding:12px 16px;border-bottom:1px solid var(--garis)}
.pesan-baris:last-child{border-bottom:0}
.pesan-baris.catatan{background:var(--emas-muda)}
.pesan-atas{display:flex;justify-content:space-between;gap:10px;font-size:11px;color:var(--teks-lemah);margin-bottom:4px}
.pesan-nama{font-weight:700;color:var(--teks)}
.pesan-isi{font-size:12.5px;line-height:1.6;white-space:pre-line}
.centang{display:flex;align-items:center;gap:6px;font-size:11.5px;margin-top:8px}
.samping .kartu{margin-bottom:14px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $ticket->subject }}</h2>
    <p>{{ $ticket->ticket_number }} &middot; {{ $ticket->full_name }} ({{ $ticket->nrp }})</p>
  </div>
  <span class="status {{ $ticket->status }}">
    {{ ['terbuka' => 'Terbuka', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'][$ticket->status] ?? $ticket->status }}
  </span>
</div>

<div class="panel">
  <div>
    <div class="thread">
      <div class="pesan-baris">
        <div class="pesan-atas"><span class="pesan-nama">{{ $ticket->full_name }} (pemohon)</span><span>{{ date('j M Y, H:i', strtotime($ticket->created_at)) }}</span></div>
        <div class="pesan-isi">{{ $ticket->description }}</div>
      </div>
      @foreach ($replies as $r)
        <div class="pesan-baris {{ $r->is_internal_note ? 'catatan' : '' }}">
          <div class="pesan-atas">
            <span class="pesan-nama">{{ $r->author_name }}{{ $r->is_internal_note ? ' — Catatan Internal' : '' }}</span>
            <span>{{ date('j M Y, H:i', strtotime($r->created_at)) }}</span>
          </div>
          <div class="pesan-isi">{{ $r->message }}</div>
        </div>
      @endforeach
    </div>

    @if ($ticket->status !== 'ditutup')
      <div class="kartu">
        <div class="kartu-judul">Balas</div>
        <form method="POST" action="{{ route('admin.helpdesk-reply', $ticket->id) }}">
          @csrf
          <div class="bidang">
            <textarea name="message" rows="3" maxlength="2000" required placeholder="Tulis balasan..."></textarea>
          </div>
          <label class="centang">
            <input type="checkbox" name="is_internal_note" value="1">
            Catatan internal (tidak terlihat oleh pegawai)
          </label>
          <div style="margin-top:10px">
            <button type="submit" class="btn">Kirim</button>
          </div>
        </form>
      </div>
    @endif
  </div>

  <div class="samping">
    <div class="kartu">
      <div class="kartu-judul">Tugaskan</div>
      <form method="POST" action="{{ route('admin.helpdesk-assign', $ticket->id) }}">
        @csrf
        <div class="bidang">
          <select name="assigned_to" required>
            <option value="">— Pilih staf HC —</option>
            @foreach ($hcStaff as $staf)
              <option value="{{ $staf->id }}" @selected($ticket->assigned_to === $staf->id)>{{ $staf->full_name }}</option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="btn luar" style="padding:7px 12px;font-size:12px">Tugaskan</button>
      </form>
    </div>

    <div class="kartu">
      <div class="kartu-judul">Ubah Status</div>
      <form method="POST" action="{{ route('admin.helpdesk-status', $ticket->id) }}">
        @csrf
        <div class="bidang">
          <select name="status" required>
            @foreach (['terbuka' => 'Terbuka', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'] as $value => $label)
              <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="btn luar" style="padding:7px 12px;font-size:12px">Perbarui Status</button>
      </form>
    </div>
  </div>
</div>
@endsection
