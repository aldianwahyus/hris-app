@extends('layouts.app')

@section('judul', 'Slip Gaji')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.rincian{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.rincian:last-child{border-bottom:0}
.rincian.total{font-weight:700;font-size:14px;padding-top:13px}
.pending{margin-top:12px;padding:11px 13px;background:var(--latar);border:1px solid var(--garis);
  border-radius:8px;font-size:11.5px;color:var(--teks-lemah);line-height:1.7}
.pending ul{margin:6px 0 0 18px}
.kosong{padding:24px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
@forelse ($slips as $s)
  @php
    $deductions = $deductionsByPayslip->get($s->id, collect());
    $additions = $additionsByPayslip->get($s->id, collect());
    $totalDeductions = $deductions->sum('amount_cents');
    $totalAdditions = $additions->sum('amount_cents');
    $bersih = $s->take_home_partial_cents - $totalDeductions + $totalAdditions;
  @endphp
  <div class="kartu">
    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-bottom:13px">
      <div class="kartu-judul" style="margin-bottom:0">Slip gaji — {{ date('F Y', strtotime($s->period)) }}</div>
      <a href="{{ route('payslip.download', $s->id) }}" class="btn luar" style="padding:6px 12px">Unduh PDF</a>
    </div>

    <div class="rincian"><span>Imbalan Kerja (PG {{ $s->person_grade }}, baris {{ $s->salary_step }})</span>
      <span class="angka">Rp{{ number_format($s->imbalan_kerja_cents / 100, 0, ',', '.') }}</span></div>
    <div class="rincian"><span>Tunjangan Jabatan</span>
      <span class="angka">Rp{{ number_format($s->tunjangan_jabatan_cents / 100, 0, ',', '.') }}</span></div>
    <div class="rincian"><span>Tunjangan Penyesuaian</span>
      <span class="angka">Rp{{ number_format($s->tunjangan_penyesuaian_cents / 100, 0, ',', '.') }}</span></div>
    @foreach ($additions as $a)
      <div class="rincian"><span>{{ \App\Modules\Payroll\Domain\AdditionType::from($a->addition_type)->label() }}</span>
        <span class="angka">Rp{{ number_format($a->amount_cents / 100, 0, ',', '.') }}</span></div>
    @endforeach
    <div class="rincian"><span>Iuran Pensiun (7%)</span>
      <span class="angka">- Rp{{ number_format($s->iuran_pensiun_pegawai_cents / 100, 0, ',', '.') }}</span></div>
    <div class="rincian"><span>Iuran THT (5%)</span>
      <span class="angka">- Rp{{ number_format($s->iuran_tht_pegawai_cents / 100, 0, ',', '.') }}</span></div>
    @if ($s->pph21_cents !== null)
      <div class="rincian"><span>PPh 21 (sementara, Gol. {{ $s->pph21_golongan }})</span>
        <span class="angka">- Rp{{ number_format($s->pph21_cents / 100, 0, ',', '.') }}</span></div>
    @endif
    @foreach ($deductions as $d)
      <div class="rincian"><span>{{ \App\Modules\Payroll\Domain\DeductionType::from($d->deduction_type)->label() }}</span>
        <span class="angka">- Rp{{ number_format($d->amount_cents / 100, 0, ',', '.') }}</span></div>
    @endforeach
    <div class="rincian total"><span>Take Home Pay</span>
      <span class="angka">Rp{{ number_format($bersih / 100, 0, ',', '.') }}</span></div>

    <div class="pending">
      Catatan penting mengenai angka di atas:
      <ul>
        @foreach (json_decode($s->pending_components, true) as $p)
          <li>{{ $p }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@empty
  <div class="kartu"><div class="kosong">Belum ada slip gaji yang disetujui.</div></div>
@endforelse
@endsection
