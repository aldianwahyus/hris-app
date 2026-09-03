@extends('layouts.app')

@section('judul', 'Requisition — '.$requisition->position_name)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending{background:var(--emas-muda);color:#7A5F0B}
.status.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected{background:#EDEDED;color:#6B6B6B}
.aksi{display:flex;gap:8px;margin-top:14px}
.ringkasan-baris{display:flex;justify-content:space-between;font-size:12.5px;padding:7px 0;border-bottom:1px solid var(--garis)}
.ringkasan-baris:last-child{border-bottom:0}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $requisition->position_name }} — {{ $requisition->office_name }}</h2>
    <p>{{ $requisition->requested_headcount }} orang</p>
  </div>
  <span class="status {{ $requisition->status }}">
    {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$requisition->status] ?? $requisition->status }}
  </span>
</div>

<div class="kartu">
  <div class="kartu-judul">Justifikasi</div>
  <p style="font-size:12.5px;line-height:1.6">{{ $requisition->justification }}</p>
  @if ($requisition->decision_note)
    <div class="ringkasan-baris"><span>Catatan Keputusan</span><span>{{ $requisition->decision_note }}</span></div>
  @endif
</div>

@if ($requisition->status === 'pending')
  @can('recruitment-requisition.decide')
    <div class="aksi">
      <form method="POST" action="{{ route('admin.recruitment-requisition-approve', $requisition->id) }}">
        @csrf
        <button type="submit" class="btn">Setujui</button>
      </form>
      <form method="POST" action="{{ route('admin.recruitment-requisition-reject', $requisition->id) }}" onsubmit="mintaAlasanTolak(this, event); return false;">
        @csrf
        <button type="submit" class="btn luar">Tolak</button>
      </form>
    </div>
  @else
    <p style="font-size:12px;color:var(--teks-lemah)">Menunggu keputusan hr_approver.</p>
  @endcan
@elseif ($requisition->status === 'approved')
  <a href="{{ route('admin.recruitment-posting-create') }}" class="btn">Buka Lowongan dari Requisition Ini</a>
@endif
@endsection
