@extends('layouts.app')

@section('judul', 'Proses Pembayaran SPPD Massal')
@section('peran', 'Admin HC / Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kepala a{font-size:11.5px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px}
.bidang-kecil{display:flex;flex-direction:column;gap:5px;min-width:200px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil select{padding:8px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);margin-bottom:16px}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:9px 10px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:9px 10px;border-bottom:1px solid var(--garis);font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.utama{padding:9px 18px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
@endsection

@section('isi')
<div class="kepala">
  <h2>Grup {{ $group->group_number }} — {{ $group->destination }}</h2>
  <p>
    Centang pegawai yang akan dibayar, pilih penandatangan dan akun jurnal, lalu proses sebagai satu batch
    · <a href="{{ route($group->payer_scope === 'hc' ? 'admin.sppd-payment.groups' : 'hr.sppd-payment.groups') }}">&larr; Kembali ke daftar grup</a>
  </p>
</div>

@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

@if ($travelers->isEmpty())
  <div class="gulir"><div class="kosong">Tidak ada pegawai yang menunggu pembayaran di grup ini.</div></div>
@else
  <div class="info">
    Pembayaran SPPD Massal dikenakan PPh 21 memakai tarif TER (berdasarkan status PTKP pegawai dan
    gaji kotor + lumpsum SPPD bulan ini), sama seperti Bekal Cuti — rincian bruto/pajak/bersih per
    pegawai tampil pada Nota Debet (bersih) dan Jurnal Slip (pajak) setelah batch diproses.
  </div>

  <form method="POST" action="{{ route($group->payer_scope === 'hc' ? 'admin.sppd-payment.process' : 'hr.sppd-payment.process', $group->id) }}"
        data-confirm="Proses pembayaran untuk seluruh pegawai yang dicentang?">
    @csrf
    <input type="hidden" name="memo_group_id" value="{{ $group->id }}">

    <div class="baris-tambah">
      <div class="bidang-kecil">
        <label>Penandatangan</label>
        <select name="signatory_employee_id" required>
          <option value="">— Pilih —</option>
          @foreach ($signatories as $s)
            <option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->nrp }})</option>
          @endforeach
        </select>
      </div>
      <div class="bidang-kecil">
        <label>Akun Jurnal Beban Lumpsum</label>
        <select name="journal_expense_account_id" required>
          <option value="">— Pilih —</option>
          @foreach ($accounts['beban'] as $a)
            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang-kecil">
        <label>Akun Jurnal Beban PPh 21</label>
        <select name="journal_tax_expense_account_id" required>
          <option value="">— Pilih —</option>
          @foreach ($accounts['beban'] as $a)
            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="bidang-kecil">
        <label>Akun Penampungan Pajak</label>
        <select name="journal_tax_account_id" required>
          <option value="">— Pilih —</option>
          @foreach ($accounts['penampungan_pajak'] as $a)
            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="gulir">
      <table>
        <thead>
          <tr><th></th><th>Pegawai</th><th>Total Anggaran</th></tr>
        </thead>
        <tbody>
          @foreach ($travelers as $t)
            @php
              $total = $t->uang_makan_cents + $t->uang_saku_cents + ($t->estimasi_hotel_cents ?? 0)
                + ($t->hotel_kompensasi_cents ?? 0) + ($t->estimasi_angkutan_setempat_cents ?? 0) + ($t->estimasi_transportasi_tujuan_cents ?? 0)
                + ($t->uang_makan_h1_cents ?? 0) + ($t->uang_saku_h1_cents ?? 0) + ($t->uang_makan_konsumsi_cents ?? 0);
            @endphp
            <tr>
              <td><input type="checkbox" name="request_ids[]" value="{{ $t->id }}"></td>
              <td>{{ $t->full_name }}<br><small style="color:var(--teks-lemah)">{{ $t->nrp }}</small></td>
              <td class="angka">Rp {{ number_format($total / 100, 0, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <button type="submit" class="utama">Proses Pembayaran</button>
  </form>
@endif
@endsection
