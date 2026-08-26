@extends('layouts.app')

@section('judul', 'Detail Payroll')
@section('peran', 'Pejabat SDM (Approver)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status-run{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;margin-left:8px}
.status-run.draft{background:var(--emas-muda);color:#7A5F0B}
.status-run.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status-run.rejected{background:var(--merah-muda);color:var(--merah)}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:top}
tbody tr:last-child td{border-bottom:0}
tfoot td{padding:12px;font-size:12.5px;font-weight:700;border-top:2px solid var(--garis)}
.rincian{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.rincian div{margin-top:1px}
.bersih{font-weight:700;color:var(--hijau-tua)}
.kembali{font-size:12.5px;color:var(--hijau);text-decoration:none;font-weight:600}
@endsection

@section('isi')
<div class="kepala">
  <h2>
    {{ $run->office_name }} — {{ date('F Y', strtotime($run->period)) }}
    <span class="status-run {{ $run->status }}">{{ ['draft' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$run->status] ?? $run->status }}</span>
  </h2>
  <p><a href="{{ route('admin.payroll-approval-queue') }}" class="kembali">&larr; Kembali ke daftar payroll</a> — daftar gaji final per pegawai setelah potongan/tambahan.</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>NRP</th><th>Nama</th><th class="angka">Take Home (partial)</th><th>Potongan</th><th>Tambahan</th><th class="angka">Total Bersih</th></tr>
    </thead>
    <tbody>
      @php $grandTotal = 0; @endphp
      @forelse ($rows as $row)
        @php
          $deductions = $deductionsByPayslip->get($row->payslip_id, collect());
          $additions = $additionsByPayslip->get($row->payslip_id, collect());
          $totalDeductions = $deductions->sum('amount_cents');
          $totalAdditions = $additions->sum('amount_cents');
          $bersih = $row->take_home_partial_cents - $totalDeductions + $totalAdditions;
          $grandTotal += $bersih;
        @endphp
        <tr>
          <td class="angka">{{ $row->nrp }}</td>
          <td>{{ $row->full_name }}</td>
          <td class="angka">Rp{{ number_format($row->take_home_partial_cents / 100, 0, ',', '.') }}</td>
          <td>
            @forelse ($deductions as $d)
              <div class="rincian">{{ \App\Modules\Payroll\Domain\DeductionType::from($d->deduction_type)->label() }}: Rp{{ number_format($d->amount_cents / 100, 0, ',', '.') }}</div>
            @empty
              <span class="rincian">—</span>
            @endforelse
          </td>
          <td>
            @forelse ($additions as $a)
              <div class="rincian">{{ \App\Modules\Payroll\Domain\AdditionType::from($a->addition_type)->label() }}: Rp{{ number_format($a->amount_cents / 100, 0, ',', '.') }}</div>
            @empty
              <span class="rincian">—</span>
            @endforelse
          </td>
          <td class="angka bersih">Rp{{ number_format($bersih / 100, 0, ',', '.') }}</td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--teks-lemah)">Belum ada slip pada run ini.</td></tr>
      @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
      <tfoot>
        <tr><td colspan="5">Total Bersih Keseluruhan</td><td class="angka">Rp{{ number_format($grandTotal / 100, 0, ',', '.') }}</td></tr>
      </tfoot>
    @endif
  </table>
</div>
@endsection
