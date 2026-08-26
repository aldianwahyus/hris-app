{{-- Penghargaan yang Pernah Diterima — self-report, tulis langsung. Butuh var: $awards. --}}
<div class="kartu">
  <div class="kartu-judul">Penghargaan yang pernah diterima</div>

  @forelse ($awards as $a)
    <div class="baca">
      <div>
        <span>{{ $a->award_name }}</span>
        @if ($a->issuer)
          <span style="color:var(--teks-lemah)"> — {{ $a->issuer }}</span>
        @endif
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        @if ($a->award_date)
          <span class="angka" style="color:var(--teks-lemah)">{{ date('j M Y', strtotime($a->award_date)) }}</span>
        @endif
        <form method="POST" action="{{ route('ess.cv.awards.destroy', $a->id) }}" data-confirm="Hapus penghargaan ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div style="padding:12px 0;color:var(--teks-lemah);font-size:12.5px">Belum ada penghargaan yang ditambahkan.</div>
  @endforelse

  <form method="POST" action="{{ route('ess.cv.awards.store') }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nama Penghargaan</label>
        <input type="text" name="award_name" maxlength="200" required>
      </div>
      <div class="bidang">
        <label>Pemberi (opsional)</label>
        <input type="text" name="issuer" maxlength="150">
      </div>
      <div class="bidang">
        <label>Tanggal (opsional)</label>
        <input type="date" name="award_date">
      </div>
    </div>
    <div class="bidang">
      <label>Keterangan (opsional)</label>
      <textarea name="description" rows="2"></textarea>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah</button>
  </form>
</div>
