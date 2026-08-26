{{-- Riwayat Kerja di Bank NTB Syariah — HR-only, tulis langsung. Butuh var: $employeeId, $internalWorkHistories. --}}
<div class="kartu">
  <div class="kartu-judul">Riwayat kerja di Bank NTB Syariah</div>

  @forelse ($internalWorkHistories as $r)
    <div class="riwayat">
      <div>
        <span>{{ $r->position_description }}</span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span class="angka" style="color:var(--teks-lemah)">
          {{ date('M Y', strtotime($r->start_date)) }} — {{ $r->end_date ? date('M Y', strtotime($r->end_date)) : 'sekarang' }}
        </span>
        <form method="POST" action="{{ route('employee-records.internal-history.destroy', [$employeeId, $r->id]) }}"
              data-confirm="Hapus riwayat ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div class="kosong">Belum ada riwayat kerja internal.</div>
  @endforelse

  <form method="POST" action="{{ route('employee-records.internal-history.store', $employeeId) }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Jabatan / Unit Kerja</label>
        <input type="text" name="position_description" maxlength="200" required>
      </div>
      <div class="bidang">
        <label>Mulai</label>
        <input type="date" name="start_date" required>
      </div>
      <div class="bidang">
        <label>Sampai (kosongkan bila masih berlangsung)</label>
        <input type="date" name="end_date">
      </div>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah</button>
  </form>
</div>
