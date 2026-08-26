{{-- Pelatihan yang Pernah Diikuti — self-report, tulis langsung. Butuh var: $trainings. --}}
<div class="kartu">
  <div class="kartu-judul">Pelatihan yang pernah diikuti</div>

  @forelse ($trainings as $t)
    <div class="baca">
      <div>
        <span>{{ $t->training_name }}</span>
        @if ($t->organizer)
          <span style="color:var(--teks-lemah)"> — {{ $t->organizer }}</span>
        @endif
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        @if ($t->start_date)
          <span class="angka" style="color:var(--teks-lemah)">
            {{ date('M Y', strtotime($t->start_date)) }}{{ $t->end_date ? ' — '.date('M Y', strtotime($t->end_date)) : '' }}
          </span>
        @endif
        <form method="POST" action="{{ route('ess.cv.trainings.destroy', $t->id) }}" data-confirm="Hapus pelatihan ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div style="padding:12px 0;color:var(--teks-lemah);font-size:12.5px">Belum ada pelatihan yang ditambahkan.</div>
  @endforelse

  <form method="POST" action="{{ route('ess.cv.trainings.store') }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nama Pelatihan</label>
        <input type="text" name="training_name" maxlength="200" required>
      </div>
      <div class="bidang">
        <label>Penyelenggara (opsional)</label>
        <input type="text" name="organizer" maxlength="150">
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Mulai (opsional)</label>
        <input type="date" name="start_date">
      </div>
      <div class="bidang">
        <label>Selesai (opsional)</label>
        <input type="date" name="end_date">
      </div>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah</button>
  </form>
</div>
