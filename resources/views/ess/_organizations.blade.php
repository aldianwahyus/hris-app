{{-- Organisasi yang Pernah Diikuti — self-report, tulis langsung. Butuh var: $organizations. --}}
<div class="kartu">
  <div class="kartu-judul">Organisasi yang pernah diikuti</div>

  @forelse ($organizations as $o)
    <div class="baca">
      <div>
        <span>{{ $o->organization_name }}</span>
        @if ($o->role)
          <span style="color:var(--teks-lemah)"> — {{ $o->role }}</span>
        @endif
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        @if ($o->start_date)
          <span class="angka" style="color:var(--teks-lemah)">
            {{ date('M Y', strtotime($o->start_date)) }}{{ $o->end_date ? ' — '.date('M Y', strtotime($o->end_date)) : '' }}
          </span>
        @endif
        <form method="POST" action="{{ route('ess.cv.organizations.destroy', $o->id) }}" data-confirm="Hapus organisasi ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div style="padding:12px 0;color:var(--teks-lemah);font-size:12.5px">Belum ada organisasi yang ditambahkan.</div>
  @endforelse

  <form method="POST" action="{{ route('ess.cv.organizations.store') }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nama Organisasi</label>
        <input type="text" name="organization_name" maxlength="200" required>
      </div>
      <div class="bidang">
        <label>Peran/Jabatan (opsional)</label>
        <input type="text" name="role" maxlength="100">
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
