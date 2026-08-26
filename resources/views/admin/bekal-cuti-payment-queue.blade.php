@extends('layouts.app')

@section('judul', 'Pembayaran Bekal Cuti')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kepala a{font-size:11.5px}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
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
.utama{padding:9px 18px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--hijau);background:var(--hijau);color:#fff}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.peringatan{color:var(--merah);font-size:11px;font-weight:600}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pembayaran bekal cuti — {{ $lingkup }}</h2>
  <p>
    Centang pegawai yang akan dibayar, pilih pejabat pengusul dan akun jurnal, lalu proses sebagai satu batch
    @if ($payerScope === 'hc')
      · <a href="{{ route('admin.bekal-cuti-queue') }}">&larr; Pilih divisi lain</a>
    @endif
  </p>
</div>

@if (session('gagal'))
  <div class="pesan gagal">{{ session('gagal') }}</div>
@endif

@if ($rows->isEmpty())
  <div class="gulir"><div class="kosong">Tidak ada bekal cuti yang menunggu pembayaran di lingkup ini.</div></div>
@else
  <div class="info">
    Jumlah bekal cuti terisi OTOMATIS sebesar 1× gaji terakhir (Imbalan Kerja + Tunjangan Tetap), sesuai SK Direksi
    BPP/1087/03/64/2026 — bukan diisi manual. Pajak PPh 21 dihitung otomatis pakai tarif TER (berdasarkan status
    PTKP pegawai dan gaji kotor + bekal cuti bulan ini) begitu batch diproses — rincian per pegawai tampil di
    halaman berikutnya sebelum dokumen dicetak.
  </div>

  <form method="POST" action="{{ route($payerScope === 'hc' ? 'admin.bekal-cuti-disburse' : 'hr.bekal-cuti.disburse') }}"
        data-confirm="Proses pembayaran bekal cuti untuk seluruh pegawai yang dicentang?">
    @csrf
    @if ($payerScope === 'hc')
      <input type="hidden" name="division" value="{{ $division }}">
    @endif

    <div class="baris-tambah">
      <div class="bidang-kecil">
        <label>Pejabat Pengusul</label>
        <select name="signatory_employee_id" required>
          <option value="">— Pilih —</option>
          @foreach ($signatories as $s)
            <option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->nrp }})</option>
          @endforeach
        </select>
      </div>
      <div class="bidang-kecil">
        <label>Akun Jurnal Beban Uang Cuti</label>
        <select name="journal_leave_expense_account_id" required>
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
        <select name="journal_tax_holding_account_id" required>
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
          <tr><th></th><th>Pegawai</th><th>Kantor</th><th>Tahun</th><th>Jumlah Bekal Cuti (1× Gaji Terakhir)</th></tr>
        </thead>
        <tbody>
          @foreach ($rows as $r)
            <tr>
              <td>
                @if ($r->preview_gross_cents !== null)
                  <input type="checkbox" name="disbursement_ids[]" value="{{ $r->id }}">
                @else
                  <input type="checkbox" disabled title="Data gaji pegawai belum lengkap">
                @endif
              </td>
              <td>{{ $r->full_name }}<br><small style="color:var(--teks-lemah)">{{ $r->nrp }}</small></td>
              <td>{{ $r->office_name }}</td>
              <td class="angka">{{ $r->year }}</td>
              <td class="angka">
                @if ($r->preview_gross_cents !== null)
                  Rp {{ number_format($r->preview_gross_cents / 100, 0, ',', '.') }}
                @else
                  <span class="peringatan">Data gaji belum lengkap — lengkapi person grade/skala gaji pegawai ini dahulu</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <button type="submit" class="utama">Proses Pembayaran</button>
  </form>
@endif
@endsection
