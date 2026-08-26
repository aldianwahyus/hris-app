{{-- Riwayat SK — HANYA-BACA (input lewat Admin SDM/SYSADMIN). Butuh var: $decisionLetters. --}}
<div class="kartu">
  <div class="kartu-judul">Riwayat SK</div>

  @php $labelJenis = ['mutasi' => 'Mutasi', 'promosi' => 'Promosi', 'sanksi' => 'Sanksi', 'lainnya' => 'Lainnya']; @endphp
  @php $labelStatus = ['pending' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak']; @endphp

  @forelse ($decisionLetters as $sk)
    <div class="baca" style="flex-direction:column;align-items:stretch;gap:4px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px">
        <div>
          <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;background:var(--hijau-muda);color:var(--hijau-tua)">{{ $labelJenis[$sk->sk_type] ?? $sk->sk_type }}</span>
          <span style="margin-left:8px;font-weight:600">{{ $sk->sk_number }}</span>
          @if ($sk->perubahan_status)
            <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;margin-left:8px;
              background:{{ $sk->perubahan_status === 'approved' ? 'var(--hijau-muda)' : ($sk->perubahan_status === 'rejected' ? 'var(--merah-muda)' : 'var(--emas-muda)') }};
              color:{{ $sk->perubahan_status === 'approved' ? 'var(--hijau-tua)' : ($sk->perubahan_status === 'rejected' ? 'var(--merah)' : '#7A5F0B') }}">
              {{ $labelStatus[$sk->perubahan_status] ?? $sk->perubahan_status }}
            </span>
          @endif
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <span class="angka" style="color:var(--teks-lemah)">{{ date('j M Y', strtotime($sk->sk_date)) }}</span>
          @if ($sk->document_path)
            <a href="{{ route('ess.cv.sk.download', $sk->id) }}" class="btn luar" style="padding:4px 10px;font-size:11px">Unduh Berkas</a>
          @endif
        </div>
      </div>
      <div style="font-size:12px;color:var(--teks-lemah)">{{ $sk->description }}</div>
    </div>
  @empty
    <div style="padding:12px 0;color:var(--teks-lemah);font-size:12.5px">Belum ada SK.</div>
  @endforelse
</div>
