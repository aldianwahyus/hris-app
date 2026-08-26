{{-- Riwayat Kesehatan — HR-only, tulis langsung. Butuh var: $employeeId, $healthRecords. --}}
<div class="kartu">
  <div class="kartu-judul">Riwayat kesehatan</div>

  @forelse ($healthRecords as $h)
    <div class="riwayat">
      <div>
        <span>{{ $h->note }}</span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span class="angka" style="color:var(--teks-lemah)">{{ date('j M Y', strtotime($h->record_date)) }}</span>
        <form method="POST" action="{{ route('employee-records.health-record.destroy', [$employeeId, $h->id]) }}"
              data-confirm="Hapus catatan ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div class="kosong">Belum ada riwayat kesehatan.</div>
  @endforelse

  <form method="POST" action="{{ route('employee-records.health-record.store', $employeeId) }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Tanggal</label>
        <input type="date" name="record_date" required>
      </div>
      <div class="bidang" style="flex:2">
        <label>Catatan</label>
        <input type="text" name="note" maxlength="2000" required>
      </div>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah</button>
  </form>
</div>
