{{-- Data Pasangan & Anak — HR-only, tulis langsung. Butuh var: $employeeId, $familyMembers. --}}
<div class="kartu">
  <div class="kartu-judul">Data pasangan &amp; anak</div>

  @forelse ($familyMembers as $f)
    <div class="riwayat">
      <div>
        <span class="laku">{{ $f->relationship_type === 'pasangan' ? 'Pasangan' : 'Anak' }}</span>
        <span style="margin-left:8px">{{ $f->full_name }}</span>
        @if ($f->birth_date)
          <span class="angka" style="color:var(--teks-lemah)"> — {{ date('j M Y', strtotime($f->birth_date)) }}</span>
        @endif
      </div>
      <form method="POST" action="{{ route('employee-records.family.destroy', [$employeeId, $f->id]) }}"
            data-confirm="Hapus data {{ $f->full_name }}?">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
      </form>
    </div>
  @empty
    <div class="kosong">Belum ada data pasangan/anak.</div>
  @endforelse

  <form method="POST" action="{{ route('employee-records.family.store', $employeeId) }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Hubungan</label>
        <select name="relationship_type" required>
          <option value="pasangan">Pasangan</option>
          <option value="anak">Anak</option>
        </select>
      </div>
      <div class="bidang">
        <label>Nama</label>
        <input type="text" name="full_name" maxlength="150" required>
      </div>
      <div class="bidang">
        <label>Tanggal Lahir (opsional)</label>
        <input type="date" name="birth_date">
      </div>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah</button>
  </form>
</div>
