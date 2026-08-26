{{-- Riwayat Pelatihan (LMS) — READ-ONLY, sumber kebenaran tetap
     lms_enrollments (lihat LmsCourseBatchController). Ditampilkan di
     sini murni untuk kenyamanan HC melihat riwayat pelatihan pegawai
     tanpa pindah halaman. Butuh var: $lmsHistory. --}}
<div class="kartu">
  <div class="kartu-judul">Riwayat pelatihan (LMS)</div>

  @forelse ($lmsHistory as $en)
    <div class="riwayat">
      <div>
        <span>{{ $en->course_title }}</span>
        <span style="color:var(--teks-lemah);font-size:11.5px"> — {{ $en->batch_code }} ({{ $en->status }})</span>
        @if ($en->completion_status === 'lulus')
          <span style="color:var(--hijau-tua);font-size:11.5px"> · Lulus @if($en->score) ({{ $en->score }}) @endif</span>
        @elseif ($en->completion_status === 'tidak_lulus')
          <span style="color:#9B2C2C;font-size:11.5px"> · Tidak Lulus</span>
        @endif
      </div>
      <span class="angka" style="color:var(--teks-lemah)">
        {{ date('j M Y', strtotime($en->start_date)) }} – {{ date('j M Y', strtotime($en->end_date)) }}
      </span>
    </div>
  @empty
    <div class="kosong">Belum ada riwayat pelatihan lewat LMS.</div>
  @endforelse
</div>
