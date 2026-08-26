@extends('layouts.app')

@section('judul', 'Peserta Jadwal Pelatihan')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.lulus{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.gagal{background:#FBE3E3;color:#9B2C2C}
.form-nilai{display:flex;gap:6px;align-items:center}
.form-nilai select,.form-nilai input{padding:6px 8px;border:1px solid var(--garis);border-radius:6px;
  font-family:inherit;font-size:12px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>{{ $batch->course_title }} — {{ $batch->batch_code }}</h2>
  <p>{{ date('j M Y', strtotime($batch->start_date)) }} – {{ date('j M Y', strtotime($batch->end_date)) }}
     @if ($batch->location) · {{ $batch->location }} @endif</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Peserta</th><th>Status</th><th>Kelulusan</th><th>Nilai</th><th>Sertifikat</th><th>Catat Kelulusan</th><th>Evaluasi</th></tr>
    </thead>
    <tbody>
      @forelse ($enrollments as $en)
        <tr>
          <td class="peg">{{ $en->full_name }}<small>{{ $en->nrp }}</small></td>
          <td><span class="tag">{{ $en->status }}</span></td>
          <td>
            @if ($en->completion_status === 'lulus')
              <span class="tag lulus">Lulus</span>
            @elseif ($en->completion_status === 'tidak_lulus')
              <span class="tag gagal">Tidak Lulus</span>
            @else
              <span class="tag">Belum dicatat</span>
            @endif
          </td>
          <td class="angka">{{ $en->score ?? '—' }}</td>
          <td>{{ $en->certificate_number ?? '—' }}</td>
          <td>
            @if ($en->status === 'approved')
              <form method="POST" action="{{ route('lms.admin.batches.record-completion', $en->id) }}" class="form-nilai">
                @csrf
                <select name="completion_status" required>
                  <option value="">Pilih...</option>
                  <option value="lulus" @selected($en->completion_status === 'lulus')>Lulus</option>
                  <option value="tidak_lulus" @selected($en->completion_status === 'tidak_lulus')>Tidak Lulus</option>
                </select>
                <input type="number" name="score" min="0" max="100" step="0.01" value="{{ $en->score }}" placeholder="Nilai" style="width:70px">
                <button type="submit" class="mini">Simpan</button>
              </form>
            @else
              <span style="font-size:11px;color:var(--teks-lemah)">Tidak disetujui</span>
            @endif
          </td>
          <td>
            @if ($en->completion_status !== null)
              <a href="{{ route('lms.admin.evaluations.show', $en->id) }}" class="mini">Level 3/4</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="kosong">Belum ada peserta yang disetujui/ditolak pada jadwal ini.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
