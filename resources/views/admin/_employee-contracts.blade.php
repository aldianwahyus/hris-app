{{-- Manajemen Kontrak (pegawai kontrak/outsource) — HR-only, tulis langsung. Butuh var: $employeeId, $contracts. --}}
<div class="kartu">
  <div class="kartu-judul">Kontrak (kontrak/outsource)</div>

  @forelse ($contracts as $c)
    <div class="riwayat">
      <div>
        <span class="laku">{{ $c->contract_type === 'kontrak' ? 'Kontrak' : 'Outsource' }}</span>
        <span style="margin-left:8px">{{ $c->contract_number }}</span>
        <span class="angka" style="color:var(--teks-lemah)"> — {{ date('j M Y', strtotime($c->start_date)) }} s.d. {{ date('j M Y', strtotime($c->end_date)) }}</span>
        <span class="tag {{ $c->status }}" style="margin-left:6px;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
          background:{{ $c->status === 'aktif' ? 'var(--hijau-muda)' : ($c->status === 'diperpanjang' ? 'var(--latar)' : 'var(--merah-muda)') }};
          color:{{ $c->status === 'aktif' ? 'var(--hijau-tua)' : ($c->status === 'diperpanjang' ? 'var(--teks-lemah)' : 'var(--merah)') }}">
          {{ ['aktif' => 'Aktif', 'diperpanjang' => 'Sudah Diperpanjang', 'berakhir' => 'Berakhir', 'diputus' => 'Diputus'][$c->status] ?? $c->status }}
        </span>
      </div>
      @if ($c->status === 'aktif')
        <div class="aksi" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
          <details>
            <summary class="btn luar" style="display:inline-block;padding:4px 10px;font-size:11px;cursor:pointer">Perpanjang</summary>
            <form method="POST" action="{{ route('employee-records.contract.renew', [$employeeId, $c->id]) }}" style="margin-top:8px">
              @csrf
              <div class="baris-bidang">
                <div class="bidang">
                  <label>No. Kontrak Baru</label>
                  <input type="text" name="contract_number" maxlength="50" required>
                </div>
                <div class="bidang">
                  <label>Mulai</label>
                  <input type="date" name="start_date" required>
                </div>
                <div class="bidang">
                  <label>Selesai</label>
                  <input type="date" name="end_date" required>
                </div>
              </div>
              <button type="submit" class="btn" style="padding:6px 12px;font-size:11.5px">Simpan Perpanjangan</button>
            </form>
          </details>
          <form method="POST" action="{{ route('employee-records.contract.status', [$employeeId, $c->id]) }}"
                data-confirm="Tandai kontrak {{ $c->contract_number }} berakhir?">
            @csrf
            <input type="hidden" name="status" value="berakhir">
            <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Tandai Berakhir</button>
          </form>
          <form method="POST" action="{{ route('employee-records.contract.status', [$employeeId, $c->id]) }}"
                data-confirm="Putus kontrak {{ $c->contract_number }} lebih awal?">
            @csrf
            <input type="hidden" name="status" value="diputus">
            <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Putus Kontrak</button>
          </form>
        </div>
      @endif
    </div>
  @empty
    <div class="kosong">Belum ada kontrak.</div>
  @endforelse

  <form method="POST" action="{{ route('employee-records.contract.store', $employeeId) }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Jenis</label>
        <select name="contract_type" required>
          <option value="kontrak">Kontrak</option>
          <option value="outsource">Outsource</option>
        </select>
      </div>
      <div class="bidang">
        <label>No. Kontrak</label>
        <input type="text" name="contract_number" maxlength="50" required>
      </div>
      <div class="bidang">
        <label>Mulai</label>
        <input type="date" name="start_date" required>
      </div>
      <div class="bidang">
        <label>Selesai</label>
        <input type="date" name="end_date" required>
      </div>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah Kontrak</button>
  </form>
</div>
