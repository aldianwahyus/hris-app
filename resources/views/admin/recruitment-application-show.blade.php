@extends('layouts.app')

@section('judul', $application->full_name)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.melamar{background:var(--emas-muda);color:#7A5F0B}
.status.seleksi_berkas{background:#DCEAFB;color:#1D4E89}
.status.wawancara{background:#EAE2F8;color:#5B2A9E}
.status.penawaran{background:#FDEBD3;color:#8A5A00}
.status.diterima{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.ditolak{background:#EDEDED;color:#6B6B6B}
.ringkasan-baris{display:flex;justify-content:space-between;font-size:12.5px;padding:7px 0;border-bottom:1px solid var(--garis)}
.ringkasan-baris:last-child{border-bottom:0}
.item-baris{background:var(--latar);border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:12px}
.tautan{color:var(--hijau);font-weight:600;text-decoration:none;font-size:12px}
.tautan:hover{text-decoration:underline}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $application->full_name }}</h2>
    <p>{{ $application->email }}{{ $application->phone ? ' · '.$application->phone : '' }} &middot; Melamar: {{ $application->posting_title }}</p>
  </div>
  <span class="status {{ $application->status }}">
    {{ ['melamar' => 'Melamar', 'seleksi_berkas' => 'Seleksi Berkas', 'wawancara' => 'Wawancara', 'penawaran' => 'Penawaran', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'][$application->status] ?? $application->status }}
  </span>
</div>

@if ($errors->any())
  <div class="pesan gagal">{{ $errors->first() }}</div>
@endif

<div class="kartu">
  <div class="kartu-judul">Kandidat</div>
  @if ($application->resume_path)
    <a href="{{ route('admin.recruitment-application-resume', $application->id) }}" class="tautan">Unduh CV</a>
  @else
    <span style="font-size:12px;color:var(--teks-lemah)">Tidak ada CV terlampir.</span>
  @endif
</div>

@unless (in_array($application->status, ['diterima', 'ditolak'], true))
  <div class="kartu">
    <div class="kartu-judul">Ubah Tahap</div>
    <form method="POST" action="{{ route('admin.recruitment-application-stage', $application->id) }}">
      @csrf
      <div class="baris-bidang">
        <div class="bidang">
          <select name="status" required>
            @foreach (['melamar' => 'Melamar', 'seleksi_berkas' => 'Seleksi Berkas', 'wawancara' => 'Wawancara', 'penawaran' => 'Penawaran', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'] as $value => $label)
              <option value="{{ $value }}" @selected($application->status === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="bidang">
          <input type="text" name="stage_notes" placeholder="Catatan (opsional)" value="{{ $application->stage_notes }}">
        </div>
      </div>
      <button type="submit" class="btn luar" style="padding:7px 12px;font-size:12px">Perbarui Tahap</button>
    </form>
  </div>
@endunless

<div class="kartu">
  <div class="kartu-judul">Wawancara</div>
  @foreach ($interviews as $iw)
    <div class="item-baris">
      <strong>{{ date('j M Y, H:i', strtotime($iw->scheduled_at)) }}</strong> — {{ $iw->location_or_link }}
      @if ($iw->interviewer_name) &middot; Pewawancara: {{ $iw->interviewer_name }} @endif
      <br>Status: {{ ['dijadwalkan' => 'Dijadwalkan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'][$iw->status] ?? $iw->status }}
      @if ($iw->feedback)
        <div style="margin-top:6px;font-style:italic">"{{ $iw->feedback }}"{{ $iw->rating ? ' — Rating: '.$iw->rating.'/5' : '' }}</div>
      @elseif ($iw->status === 'dijadwalkan')
        <form method="POST" action="{{ route('admin.recruitment-application-interview-feedback', [$application->id, $iw->id]) }}" style="margin-top:8px">
          @csrf
          <div class="baris-bidang">
            <div class="bidang"><textarea name="feedback" rows="2" placeholder="Hasil wawancara" required></textarea></div>
            <div class="bidang">
              <select name="rating">
                <option value="">Rating —</option>
                @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
              </select>
            </div>
          </div>
          <button type="submit" class="btn luar" style="padding:6px 10px;font-size:11.5px">Simpan Hasil</button>
        </form>
      @endif
    </div>
  @endforeach

  @unless (in_array($application->status, ['diterima', 'ditolak'], true))
    <details>
      <summary class="btn luar" style="display:inline-block;padding:7px 12px;font-size:12px">+ Jadwalkan Wawancara</summary>
      <form method="POST" action="{{ route('admin.recruitment-application-interview', $application->id) }}" style="margin-top:10px">
        @csrf
        <div class="baris-bidang">
          <div class="bidang">
            <label>Tanggal & Waktu</label>
            <input type="datetime-local" name="scheduled_at" required>
          </div>
          <div class="bidang">
            <label>Lokasi/Tautan</label>
            <input type="text" name="location_or_link" required>
          </div>
        </div>
        <div class="bidang">
          <label>Pewawancara</label>
          <select name="interviewer_employee_id">
            <option value="">— Opsional —</option>
            @foreach ($employees as $e)
              <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->nrp }})</option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="btn" style="padding:7px 12px;font-size:12px">Jadwalkan</button>
      </form>
    </details>
  @endunless
</div>

<div class="kartu">
  <div class="kartu-judul">Tawaran Kerja</div>
  @forelse ($offers as $o)
    <div class="item-baris">
      {{ $o->position_name }} — {{ $o->office_name }}
      @if ($o->proposed_salary_notes) <br>{{ $o->proposed_salary_notes }} @endif
      <div class="ringkasan-baris"><span>Status</span><span>{{ ['menunggu' => 'Menunggu Respons', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'][$o->status] ?? $o->status }}</span></div>
      @if ($o->status === 'menunggu')
        <div class="ringkasan-baris"><span>Tautan Kandidat</span><span>{{ route('careers.offer', $o->response_token) }}</span></div>
      @endif
    </div>
  @empty
    <p style="font-size:12px;color:var(--teks-lemah)">Belum ada tawaran.</p>
  @endforelse

  @if ($application->status === 'penawaran' && ! $offers->contains(fn ($o) => $o->status === 'menunggu'))
    <details>
      <summary class="btn luar" style="display:inline-block;padding:7px 12px;font-size:12px">+ Buat Tawaran</summary>
      <form method="POST" action="{{ route('admin.recruitment-application-offer', $application->id) }}" style="margin-top:10px">
        @csrf
        <div class="baris-bidang">
          <div class="bidang">
            <label>Posisi</label>
            <select name="proposed_position_id" required>
              @foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
          </div>
          <div class="bidang">
            <label>Kantor</label>
            <select name="proposed_office_id" required>
              @foreach ($offices as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
          </div>
        </div>
        <div class="bidang">
          <label>Catatan Gaji (opsional)</label>
          <textarea name="proposed_salary_notes" rows="2"></textarea>
        </div>
        <button type="submit" class="btn" style="padding:7px 12px;font-size:12px">Kirim Tawaran</button>
      </form>
    </details>
  @endif
</div>

@if ($application->status === 'diterima')
  <div class="kartu">
    <div class="kartu-judul">Proses Jadi Pegawai Baru</div>
    <p style="font-size:12px;color:var(--teks-lemah);margin-bottom:10px">
      Mengusulkan kandidat ini sebagai pegawai baru — akan masuk antrean persetujuan pegawai baru yang sudah ada, menunggu keputusan hr_approver.
    </p>
    <form method="POST" action="{{ route('admin.recruitment-application-convert', $application->id) }}">
      @csrf
      <div class="baris-bidang">
        <div class="bidang">
          <label>NRP Baru</label>
          <input type="text" name="nrp" required>
        </div>
        <div class="bidang">
          <label>Tanggal Bergabung</label>
          <input type="date" name="join_date" required>
        </div>
      </div>
      <button type="submit" class="btn">Usulkan Sebagai Pegawai Baru</button>
    </form>
  </div>
@endif
@endsection
