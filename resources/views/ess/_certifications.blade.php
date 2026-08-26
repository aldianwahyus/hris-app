{{-- Sertifikasi yang Pernah Diikuti — self-report, tulis langsung. Butuh var: $certifications. --}}
<div class="kartu">
  <div class="kartu-judul">Sertifikasi yang pernah diikuti</div>

  @forelse ($certifications as $c)
    <div class="baca">
      <div>
        <span>{{ $c->certification_name }}</span>
        @if ($c->issuer)
          <span style="color:var(--teks-lemah)"> — {{ $c->issuer }}</span>
        @endif
        @if ($c->certificate_number)
          <span class="angka" style="color:var(--teks-lemah)"> ({{ $c->certificate_number }})</span>
        @endif
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        @if ($c->expiry_date)
          <span class="angka" style="color:var(--teks-lemah)">berlaku s.d. {{ date('M Y', strtotime($c->expiry_date)) }}</span>
        @endif
        <form method="POST" action="{{ route('ess.cv.certifications.destroy', $c->id) }}" data-confirm="Hapus sertifikasi ini?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn luar" style="padding:4px 10px;font-size:11px">Hapus</button>
        </form>
      </div>
    </div>
  @empty
    <div style="padding:12px 0;color:var(--teks-lemah);font-size:12.5px">Belum ada sertifikasi yang ditambahkan.</div>
  @endforelse

  <form method="POST" action="{{ route('ess.cv.certifications.store') }}" style="margin-top:13px">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label>Nama Sertifikasi</label>
        <input type="text" name="certification_name" maxlength="200" required>
      </div>
      <div class="bidang">
        <label>Penerbit (opsional)</label>
        <input type="text" name="issuer" maxlength="150">
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label>Tanggal Terbit (opsional)</label>
        <input type="date" name="issued_date">
      </div>
      <div class="bidang">
        <label>Tanggal Kedaluwarsa (opsional)</label>
        <input type="date" name="expiry_date">
      </div>
      <div class="bidang">
        <label>Nomor Sertifikat (opsional)</label>
        <input type="text" name="certificate_number" maxlength="100">
      </div>
    </div>
    <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Tambah</button>
  </form>
</div>
