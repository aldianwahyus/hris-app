@extends('layouts.app')

@section('judul', 'Potongan Gaji')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.alat{display:flex;gap:8px;align-items:flex-start;margin-bottom:20px;padding:13px;
  background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);flex-wrap:wrap}
.alat .ket{font-size:11px;color:var(--teks-lemah);width:100%}
.alat input[type=file]{font-size:12px}
.kartu-pegawai{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);
  padding:14px 16px;margin-bottom:12px}
.baris-pegawai{display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:6px;
  margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid var(--garis)}
.baris-pegawai .nama{font-weight:700;font-size:13.5px}
.baris-pegawai .nrp{font-size:11.5px;color:var(--teks-lemah);margin-left:6px}
.baris-pegawai .angka{font-family:'JetBrains Mono',monospace;font-size:12.5px}
table.potongan{width:100%;border-collapse:collapse;margin-bottom:10px}
table.potongan th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;color:var(--teks-lemah);padding:6px 8px;border-bottom:1px solid var(--garis)}
table.potongan td{padding:7px 8px;font-size:12px;border-bottom:1px solid var(--garis)}
.kosong-kecil{padding:8px;color:var(--teks-lemah);font-size:12px;font-style:italic}
.subjudul{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
  color:var(--teks-lemah);margin-bottom:6px}
.form-tambah{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.form-tambah select,.form-tambah input{padding:6px 9px;border:1px solid var(--garis);
  border-radius:6px;font-family:inherit;font-size:12px}
.form-tambah input[name=amount]{width:130px}
.form-tambah input[name=note]{width:180px}
.mini{padding:5px 10px;border-radius:6px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks)}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.mini.bahaya{color:var(--merah)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Potongan gaji — {{ date('F Y', strtotime($run->period)) }}</h2>
  <p>Draf ini masih berstatus DRAFT — potongan dapat diubah bebas sampai disetujui Pejabat SDM.</p>
</div>

<div class="alat">
  <a class="mini" href="{{ route('hr.payroll-deduction.template', $run->id) }}">Unduh Template Impor (CSV)</a>
  <form method="POST" action="{{ route('hr.payroll-deduction.import', $run->id) }}" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center">
    @csrf
    <input type="file" name="berkas" accept=".csv,.txt" required>
    <button class="mini utama" type="submit">Impor Potongan</button>
  </form>
  <span class="ket">Template sudah berisi data pegawai + penghasilan sebelum potongan. Impor ulang MENGGANTIKAN seluruh potongan pegawai yang ada di berkas (bukan menambah).</span>
</div>

@foreach ($rows as $row)
  @php
    $deductions = $deductionsByPayslip->get($row->payslip_id, collect());
    $additions = $additionsByPayslip->get($row->payslip_id, collect());
  @endphp
  <div class="kartu-pegawai">
    <div class="baris-pegawai">
      <div><span class="nama">{{ $row->full_name }}</span><span class="nrp">{{ $row->nrp }}</span></div>
      <div class="angka">Penghasilan sebelum potongan: Rp{{ number_format($row->take_home_partial_cents / 100, 0, ',', '.') }}</div>
    </div>

    <div class="subjudul">Potongan</div>
    @if ($deductions->isEmpty())
      <div class="kosong-kecil">Belum ada potongan.</div>
    @else
      <table class="potongan">
        <thead><tr><th>Jenis</th><th>Jumlah</th><th>Catatan</th><th></th></tr></thead>
        <tbody>
          @foreach ($deductions as $d)
            <tr>
              <td>{{ \App\Modules\Payroll\Domain\DeductionType::from($d->deduction_type)->label() }}</td>
              <td class="angka">Rp{{ number_format($d->amount_cents / 100, 0, ',', '.') }}</td>
              <td>{{ $d->note }}</td>
              <td>
                <form method="POST" action="{{ route('hr.payroll-deduction.destroy', [$run->id, $row->payslip_id, $d->id]) }}">
                  @csrf
                  @method('DELETE')
                  <button class="mini bahaya" type="submit" data-confirm="Hapus potongan ini?">Hapus</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    <form method="POST" action="{{ route('hr.payroll-deduction.store', [$run->id, $row->payslip_id]) }}" class="form-tambah">
      @csrf
      <select name="deduction_type" required>
        @foreach ($deductionTypes as $type)
          <option value="{{ $type->value }}">{{ $type->label() }}</option>
        @endforeach
      </select>
      <input type="number" name="amount" min="1" step="1" placeholder="Jumlah (Rp)" required>
      <input type="text" name="note" placeholder="Catatan (opsional)" maxlength="500">
      <button class="mini" type="submit">Tambah Potongan</button>
    </form>

    <div class="subjudul" style="margin-top:14px">Tambahan Penghasilan</div>
    @if ($additions->isEmpty())
      <div class="kosong-kecil">Belum ada tambahan penghasilan.</div>
    @else
      <table class="potongan">
        <thead><tr><th>Jenis</th><th>Jumlah</th><th>Catatan</th><th></th></tr></thead>
        <tbody>
          @foreach ($additions as $a)
            <tr>
              <td>{{ \App\Modules\Payroll\Domain\AdditionType::from($a->addition_type)->label() }}</td>
              <td class="angka">Rp{{ number_format($a->amount_cents / 100, 0, ',', '.') }}</td>
              <td>{{ $a->note }}</td>
              <td>
                <form method="POST" action="{{ route('hr.payroll-deduction.destroy-tambahan', [$run->id, $row->payslip_id, $a->id]) }}">
                  @csrf
                  @method('DELETE')
                  <button class="mini bahaya" type="submit" data-confirm="Hapus tambahan penghasilan ini?">Hapus</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    <form method="POST" action="{{ route('hr.payroll-deduction.store-tambahan', [$run->id, $row->payslip_id]) }}" class="form-tambah">
      @csrf
      <select name="addition_type" required>
        @foreach ($additionTypes as $type)
          <option value="{{ $type->value }}">{{ $type->label() }}</option>
        @endforeach
      </select>
      <input type="number" name="amount" min="1" step="1" placeholder="Jumlah (Rp)" required>
      <input type="text" name="note" placeholder="Catatan (opsional)" maxlength="500">
      <button class="mini" type="submit">Tambah Penghasilan</button>
    </form>
  </div>
@endforeach
@endsection
