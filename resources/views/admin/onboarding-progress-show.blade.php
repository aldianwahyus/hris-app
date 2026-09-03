@extends('layouts.app')

@section('judul', 'Onboarding — '.$checklist->full_name)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.berjalan{background:var(--emas-muda);color:#7A5F0B}
.kategori-judul{font-size:12px;font-weight:700;color:var(--teks-lemah);text-transform:uppercase;
  letter-spacing:.06em;margin:18px 0 8px}
.item-baris{background:var(--putih);border:1px solid var(--garis);border-radius:8px;
  padding:12px;margin-bottom:8px;display:flex;align-items:flex-start;gap:12px}
.item-teks{flex:1}
.item-nama{font-size:12.5px;font-weight:600}
.item-info{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.item-catatan{font-size:11px;color:var(--teks-lemah);margin-top:4px;font-style:italic}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $checklist->full_name }}</h2>
    <p>{{ $checklist->nrp }} &middot; Dimulai {{ date('j M Y', strtotime($checklist->started_at)) }}</p>
  </div>
  <span class="status {{ $checklist->completed_at ? 'selesai' : 'berjalan' }}">
    {{ $checklist->completed_at ? 'Selesai' : 'Berjalan' }}
  </span>
</div>

@foreach (['it' => 'IT', 'hc' => 'HC', 'fasilitas' => 'Fasilitas', 'lainnya' => 'Lainnya'] as $kategori => $labelKategori)
  @php $itemsKategori = $items->where('category', $kategori); @endphp
  @if ($itemsKategori->isNotEmpty())
    <div class="kategori-judul">{{ $labelKategori }}</div>
    @foreach ($itemsKategori as $item)
      <div class="item-baris">
        <form method="POST" action="{{ route('admin.onboarding-item-complete', [$checklist->id, $item->id]) }}">
          @csrf
          <input type="hidden" name="is_done" value="{{ $item->is_done ? '0' : '1' }}">
          <button type="submit" class="mini" style="padding:5px 10px;font-size:11.5px">
            {{ $item->is_done ? 'Batalkan' : 'Selesai' }}
          </button>
        </form>
        <div class="item-teks">
          <div class="item-nama" style="{{ $item->is_done ? 'text-decoration:line-through;color:var(--teks-lemah)' : '' }}">{{ $item->item_name }}</div>
          @if ($item->is_done)
            <div class="item-info">Diselesaikan oleh {{ $item->done_by_name }} pada {{ date('j M Y, H:i', strtotime($item->done_at)) }}</div>
          @endif
          @if ($item->notes)
            <div class="item-catatan">{{ $item->notes }}</div>
          @endif
        </div>
      </div>
    @endforeach
  @endif
@endforeach
@endsection
