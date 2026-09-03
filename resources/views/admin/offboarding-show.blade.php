@extends('layouts.app')

@section('judul', 'Pemisahan — '.$separation->full_name)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending{background:var(--emas-muda);color:#7A5F0B}
.status.approved{background:#DCEAFB;color:#1D4E89}
.status.selesai{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected{background:#EDEDED;color:#6B6B6B}
.aksi{display:flex;gap:8px}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.kategori-judul{font-size:12px;font-weight:700;color:var(--teks-lemah);text-transform:uppercase;
  letter-spacing:.06em;margin:18px 0 8px}
.item-baris{background:var(--putih);border:1px solid var(--garis);border-radius:8px;
  padding:12px;margin-bottom:8px;display:flex;align-items:flex-start;gap:12px}
.item-teks{flex:1}
.item-nama{font-size:12.5px;font-weight:600}
.item-info{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.ringkasan-baris{display:flex;justify-content:space-between;font-size:12px;padding:6px 0;border-bottom:1px solid var(--garis)}
.ringkasan-baris:last-child{border-bottom:0}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $separation->full_name }}</h2>
    <p>{{ $separation->nrp }} &middot; {{ $separationTypes[$separation->separation_type] ?? $separation->separation_type }}
      &middot; Terakhir bekerja {{ date('j M Y', strtotime($separation->requested_last_date)) }}</p>
  </div>
  <span class="status {{ $separation->status }}">
    {{ ['pending' => 'Menunggu', 'approved' => 'Proses Clearance', 'selesai' => 'Selesai', 'rejected' => 'Ditolak'][$separation->status] ?? $separation->status }}
  </span>
</div>

<div class="kartu" style="margin-bottom:16px">
  <div class="kartu-judul">Alasan</div>
  <p style="font-size:12.5px;line-height:1.6">{{ $separation->reason }}</p>
  @if ($separation->decision_note)
    <div class="ringkasan-baris"><span>Catatan Keputusan</span><span>{{ $separation->decision_note }}</span></div>
  @endif
</div>

@if ($separation->status === 'pending')
  <div class="aksi" style="margin-bottom:16px">
    <form method="POST" action="{{ route('admin.offboarding-approve', $separation->id) }}">
      @csrf
      <button type="submit" class="btn">Setujui</button>
    </form>
    <form method="POST" action="{{ route('admin.offboarding-reject', $separation->id) }}" onsubmit="mintaAlasanTolak(this, event); return false;">
      @csrf
      <button type="submit" class="btn luar">Tolak</button>
    </form>
  </div>
@endif

@if (in_array($separation->status, ['approved', 'selesai'], true))
  @php $belumSelesai = $items->where('is_done', false)->count(); @endphp

  @if ($separation->status === 'approved')
    <div class="info">
      @if ($belumSelesai > 0)
        Masih ada {{ $belumSelesai }} item clearance yang belum selesai sebelum pemisahan dapat dituntaskan.
      @else
        Seluruh item clearance sudah selesai — pemisahan dapat dituntaskan.
      @endif
    </div>
    <form method="POST" action="{{ route('admin.offboarding-complete', $separation->id) }}" data-confirm="Tuntaskan pemisahan ini? Akun pegawai akan dinonaktifkan dan tidak bisa login lagi." style="margin-bottom:16px">
      @csrf
      <button type="submit" class="btn" @disabled($belumSelesai > 0)>Tuntaskan Pemisahan</button>
    </form>
  @endif

  @foreach (['aset' => 'Aset', 'it' => 'IT', 'keuangan' => 'Keuangan', 'hc' => 'HC', 'lainnya' => 'Lainnya'] as $kategori => $labelKategori)
    @php $itemsKategori = $items->where('category', $kategori); @endphp
    @if ($itemsKategori->isNotEmpty())
      <div class="kategori-judul">{{ $labelKategori }}</div>
      @foreach ($itemsKategori as $item)
        <div class="item-baris">
          <form method="POST" action="{{ route('admin.offboarding-item-complete', [$separation->id, $item->id]) }}">
            @csrf
            <input type="hidden" name="is_done" value="{{ $item->is_done ? '0' : '1' }}">
            <button type="submit" class="mini" style="padding:5px 10px;font-size:11.5px" @disabled($separation->status === 'selesai')>
              {{ $item->is_done ? 'Batalkan' : 'Selesai' }}
            </button>
          </form>
          <div class="item-teks">
            <div class="item-nama" style="{{ $item->is_done ? 'text-decoration:line-through;color:var(--teks-lemah)' : '' }}">{{ $item->item_name }}</div>
            @if ($item->is_done)
              <div class="item-info">Diselesaikan oleh {{ $item->done_by_name }} pada {{ date('j M Y, H:i', strtotime($item->done_at)) }}</div>
            @endif
          </div>
        </div>
      @endforeach
    @endif
  @endforeach

  <div class="kartu" style="margin-top:16px">
    <div class="kartu-judul">Wawancara Keluar</div>
    @if ($exitInterview)
      <div class="ringkasan-baris"><span>Alasan Detail</span><span>{{ $exitInterview->reason_detail ?? '—' }}</span></div>
      <div class="ringkasan-baris"><span>Tingkat Kepuasan</span><span>{{ $exitInterview->satisfaction_rating ? $exitInterview->satisfaction_rating.'/5' : '—' }}</span></div>
      <div class="ringkasan-baris"><span>Merekomendasikan Bank NTB Syariah</span><span>{{ is_null($exitInterview->would_recommend) ? '—' : ($exitInterview->would_recommend ? 'Ya' : 'Tidak') }}</span></div>
      <div class="ringkasan-baris"><span>Komentar</span><span>{{ $exitInterview->comments ?? '—' }}</span></div>
    @else
      <p style="font-size:12px;color:var(--teks-lemah);margin-bottom:10px">Belum diisi. Pegawai dapat mengisi sendiri lewat ESS, atau HC dapat mengisikan di bawah ini.</p>
      <form method="POST" action="{{ route('admin.offboarding-exit-interview-store', $separation->id) }}">
        @csrf
        <div class="bidang">
          <label>Alasan Detail</label>
          <textarea name="reason_detail" rows="2" maxlength="1000"></textarea>
        </div>
        <div class="baris-bidang">
          <div class="bidang">
            <label>Tingkat Kepuasan (1-5)</label>
            <select name="satisfaction_rating">
              <option value="">—</option>
              @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}">{{ $i }}</option>
              @endfor
            </select>
          </div>
          <div class="bidang">
            <label>Merekomendasikan?</label>
            <select name="would_recommend">
              <option value="">—</option>
              <option value="1">Ya</option>
              <option value="0">Tidak</option>
            </select>
          </div>
        </div>
        <div class="bidang">
          <label>Komentar</label>
          <textarea name="comments" rows="2" maxlength="1000"></textarea>
        </div>
        <button type="submit" class="btn luar" style="padding:7px 12px;font-size:12px">Simpan Wawancara Keluar</button>
      </form>
    @endif
  </div>
@endif
@endsection
