{{-- Riwayat Kerja di Luar Bank NTB Syariah — HR-only, tulis langsung. Butuh var: $employeeId, $externalWorkHistories. --}}
<div class="kartu">
  <div class="kartu-judul">Riwayat kerja di luar Bank NTB Syariah</div>

  @forelse ($externalWorkHistories as $r)
    <div class="riwayat">
      <div>
        <span>{{ $r->position }}</span>
        <span style="margin-left:8px;color:var(--teks-lemah)">{{ $r->company_name }}</span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span class="angka" style="color:var(--teks-lemah)">
          {{ date('M Y', strtotime($r->start_date)) }} — {{ $r->end_date ? date('M Y', strtotime($r->end_date)) : 'sekarang' }}
        </span>
        <form method="POST" action="{{ route('employee-records.external-history.destroy', [$employeeId, $r->id]) }}"
              data-confirm="Hapus riwayat ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div class="kosong">Belum ada riwayat kerja eksternal.</div>
  @endforelse

  <form method="POST" action="{{ route('employee-records.external-history.store', $employeeId) }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nama Perusahaan</label>
        <input type="text" name="company_name" maxlength="150" required>
      </div>
      <div class="bidang">
        <label>Jabatan</label>
        <input type="text" name="position" maxlength="150" required>
      </div>
    </div>
    <div class="baris-bidang">
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
