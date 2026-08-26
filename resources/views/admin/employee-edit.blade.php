@extends('layouts.app')

@section('judul', 'Ubah Data Pegawai')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.menunggu{padding:11px 13px;background:var(--emas-muda);border:1px solid #E8D9A0;
  border-radius:8px;font-size:11.5px;color:#6B540A;margin-bottom:13px;line-height:1.6}
.riwayat{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--garis);font-size:12px}
.riwayat:last-child{border-bottom:0}
.riwayat .laku{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.riwayat .laku.rejected{background:var(--merah-muda);color:var(--merah)}
.riwayat .laku.submitted{background:var(--emas-muda);color:#7A5F0B}
.kosong{padding:16px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Ubah data — {{ $employee->full_name }} <span class="angka" style="color:var(--teks-lemah)">({{ $employee->nrp }})</span></h2>
  <p>Perubahan tidak langsung tersimpan — menunggu persetujuan hr_approver</p>
</div>

@if ($pendingRequests->isNotEmpty())
  <div class="menunggu">
    Ada {{ $pendingRequests->count() }} pengajuan yang masih menunggu keputusan hr_approver untuk pegawai ini.
    Pengajuan baru tetap dapat dikirim, tapi akan diputus terpisah.
  </div>
@endif

<div class="kartu">
  <div class="kartu-judul">Ajukan perubahan</div>
  @include('admin._employee-edit-form', [
    'updateRoute' => route('hr.employees.update', $employee->id),
    'backRoute' => route('hr.employees'),
  ])
</div>

<div class="kartu">
  <div class="kartu-judul">Riwayat perubahan</div>
  @forelse ($history as $h)
    @php $new = json_decode($h->new_values ?? 'null', true); @endphp
    <div class="riwayat">
      <div>
        <span class="laku {{ $h->action }}">{{ $h->action }}</span>
        @if ($new)
          <span style="margin-left:8px">{{ implode(', ', array_keys($new)) }}</span>
        @endif
      </div>
      <span class="angka" style="color:var(--teks-lemah)">{{ date('j M Y H:i', strtotime($h->occurred_at)) }}</span>
    </div>
  @empty
    <div class="kosong">Belum ada riwayat perubahan.</div>
  @endforelse
</div>

@include('admin._employee-family-members', ['employeeId' => $employeeId, 'familyMembers' => $familyMembers])
@include('admin._employee-internal-work-history', ['employeeId' => $employeeId, 'internalWorkHistories' => $internalWorkHistories])
@include('admin._employee-external-work-history', ['employeeId' => $employeeId, 'externalWorkHistories' => $externalWorkHistories])
@include('admin._employee-health-records', ['employeeId' => $employeeId, 'healthRecords' => $healthRecords])
@include('admin._employee-lms-history', ['lmsHistory' => $lmsHistory])
@endsection
