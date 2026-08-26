@extends('layouts.app')

@section('judul', 'Surat Keputusan')
@section('peran', $bankWide ? 'Admin Sistem (IT)' : 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px}
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
.jenis{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.status-perubahan{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status-perubahan.pending{background:var(--emas-muda);color:#7A5F0B}
.status-perubahan.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status-perubahan.rejected{background:var(--merah-muda);color:var(--merah)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.mini{padding:5px 10px;border-radius:6px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks)}
.mini:hover{background:var(--latar)}
.mini.bahaya{color:var(--merah)}
.aksi{display:flex;gap:6px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Surat Keputusan</h2>
    <p>{{ $bankWide ? 'Seluruh kantor (bank-wide)' : 'Lingkup kantor Anda' }} — Mutasi/Promosi mengusulkan perubahan data induk, menunggu persetujuan hr_approver.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('sk.create') }}" class="btn">+ Buat SK</a>
    <a href="{{ route('sk.salary-change.create') }}" class="btn luar">+ Buat SK Perubahan Gaji</a>
  </div>
</div>

@php $labelJenis = ['mutasi' => 'Mutasi', 'promosi' => 'Promosi', 'sanksi' => 'Sanksi', 'perubahan_gaji' => 'Perubahan Gaji', 'lainnya' => 'Lainnya']; @endphp
@php $labelStatus = ['pending' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak']; @endphp

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Nomor SK</th><th>Jenis</th><th>Pegawai</th><th>Tanggal</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($decisionLetters as $sk)
        <tr>
          <td class="angka">{{ $sk->sk_number }}</td>
          <td><span class="jenis">{{ $labelJenis[$sk->sk_type] ?? $sk->sk_type }}</span></td>
          <td class="peg">{{ $sk->full_name }}<small>{{ $sk->nrp }}{{ $bankWide && isset($sk->office_name) ? ' — '.$sk->office_name : '' }}</small></td>
          <td class="angka">{{ date('j M Y', strtotime($sk->sk_date)) }}</td>
          <td>
            @if ($sk->perubahan_status)
              <span class="status-perubahan {{ $sk->perubahan_status }}">{{ $labelStatus[$sk->perubahan_status] ?? $sk->perubahan_status }}</span>
            @else
              —
            @endif
          </td>
          <td>
            <div class="aksi">
              @if ($sk->document_path)
                <a href="{{ route('sk.download', $sk->id) }}" class="mini">Unduh</a>
              @endif
              <form method="POST" action="{{ route('sk.destroy', $sk->id) }}" data-confirm="Hapus SK ini?">
                @csrf
                @method('DELETE')
                <button type="submit" class="mini bahaya">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Belum ada SK.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
