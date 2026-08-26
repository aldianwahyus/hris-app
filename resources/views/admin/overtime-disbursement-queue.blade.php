@extends('layouts.app')

@section('judul', 'Pembayaran Lembur')
@section('peran', $lingkup === 'Kantor Pusat' ? 'Admin HC' : 'Admin Cabang')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.bayar{display:flex;gap:6px}
.bayar input{padding:6px 9px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:12px;width:160px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--hijau);color:#fff}
.mini:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pembayaran lembur — {{ $lingkup }}</h2>
  <p>Hanya pengajuan yang SUDAH disetujui Pimpinan Kantor (tahap 2) yang muncul di sini.</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>No. SPKL</th><th>Pegawai</th><th>Kantor</th><th>Tgl Kerja</th><th>Jam</th><th>Nilai</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="angka">{{ $r->spkl_number }}</td>
          <td>{{ $r->full_name }} <span class="angka" style="color:var(--teks-lemah)">({{ $r->nrp }})</span></td>
          <td>{{ $r->office_name }}</td>
          <td class="angka">{{ $r->work_date }}</td>
          <td class="angka">{{ $r->payable_hours }}</td>
          <td class="angka">Rp{{ number_format($r->amount_cents / 100, 0, ',', '.') }}</td>
          <td>
            <form method="POST" action="{{ route($lingkup === 'Kantor Pusat' ? 'admin.overtime-disburse' : 'hr.overtime-disbursement.disburse', $r->id) }}" class="bayar">
              @csrf
              <input type="text" name="disbursement_reference" placeholder="No. referensi transfer" required>
              <button class="mini" type="submit">Bayar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="kosong">Tidak ada pengajuan lembur yang menunggu pembayaran.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
