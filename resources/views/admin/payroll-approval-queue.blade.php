@extends('layouts.app')

@section('judul', 'Persetujuan Payroll')
@section('peran', 'Pejabat SDM (Approver)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.aksi{display:flex;gap:6px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s;
  text-decoration:none;color:var(--teks);display:inline-block}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.massal{display:flex;gap:8px;align-items:center;margin-bottom:16px;padding:13px;
  background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);flex-wrap:wrap}
.massal input{padding:7px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.massal .ket{font-size:11px;color:var(--teks-lemah);width:100%}
.tinjau{margin-bottom:16px;padding:13px;background:var(--emas-muda);border:1px solid #E8D9A0;border-radius:var(--r)}
.tinjau h3{font-size:12.5px;font-weight:700;color:#6B540A;margin-bottom:8px}
.tinjau table{background:var(--putih);border-radius:7px;overflow:hidden}
.tinjau .centang{display:flex;align-items:center;gap:7px;margin-top:10px;font-size:12px;font-weight:600;color:#6B540A}
.tutup{background:var(--merah-muda);border-color:#F3C6C2}
.tutup h3{color:var(--merah)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Persetujuan payroll</h2>
  <p>Seluruh draf dari semua kantor (BANK_WIDE) yang menunggu keputusan</p>
</div>

@if ($pendingSalaryChanges->isNotEmpty())
  <div class="tinjau">
    <h3>SK Perubahan Gaji menunggu persetujuan — tinjau dulu sebelum generate</h3>
    <table>
      <thead><tr><th>Nomor SK</th><th>Pegawai</th><th>Tanggal SK</th></tr></thead>
      <tbody>
        @foreach ($pendingSalaryChanges as $p)
          <tr>
            <td class="angka">{{ $p->sk_number }}</td>
            <td>{{ $p->full_name }} ({{ $p->nrp }})</td>
            <td class="angka">{{ date('j M Y', strtotime($p->sk_date)) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <label class="centang">
      <input type="checkbox" id="cek-tinjau-gaji"> Saya sudah meninjau daftar di atas — gaji pegawai ini masih memakai nilai lama sampai SK-nya disetujui.
    </label>
  </div>
@endif

<form method="POST" action="{{ route('admin.payroll-generate-bulk') }}" class="massal">
  @csrf
  <label for="period" style="font-size:12.5px;font-weight:600">Generate massal —</label>
  <input type="month" name="period" id="period" required>
  <button type="submit" class="btn" id="btn-generate" style="padding:7px 14px" {{ $pendingSalaryChanges->isNotEmpty() ? 'disabled' : '' }}>Generate Semua Kantor</button>
  <span class="ket">Membuat draf payroll untuk seluruh kantor yang punya pegawai tetap sekaligus — kantor yang sudah punya draf periode ini otomatis dilewati.</span>
</form>

<form method="POST" action="{{ route('admin.payroll-close-period') }}" class="massal tutup">
  @csrf
  <label for="period-tutup" style="font-size:12.5px;font-weight:600">Tutup periode —</label>
  <input type="month" name="period" id="period-tutup" required>
  <button type="submit" class="btn"
    data-confirm="Tutup input potongan SELURUH kantor untuk periode ini? Admin cabang tidak akan bisa mengubah potongan lagi sampai run kantornya dibuka kembali satu per satu."
    style="padding:7px 14px;background:var(--merah)">Tutup Periode Input Potongan</button>
  <span class="ket">Menyetujui sekaligus seluruh draf payroll (Kantor Pusat, KC, KCP) pada periode yang dipilih — mengunci akses input potongan Admin Cabang untuk periode itu.</span>
</form>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kantor</th><th>Periode</th><th>Dibuat oleh</th><th>Jumlah Slip</th><th>Total (partial)</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($runs as $r)
        <tr>
          <td>{{ $r->office_name }}</td>
          <td class="angka">{{ date('F Y', strtotime($r->period)) }}</td>
          <td>{{ $r->maker_name }}</td>
          <td class="angka">{{ $r->jumlah_slip }}</td>
          <td class="angka">Rp{{ number_format($r->total_take_home_partial / 100, 0, ',', '.') }}</td>
          <td>
            <div class="aksi">
              <a href="{{ route('admin.payroll-run-detail', $r->id) }}" class="mini">Detail</a>
              <form method="POST" action="{{ route('admin.payroll-approve', $r->id) }}">
                @csrf
                <button class="mini utama" type="submit">Setujui</button>
              </form>
              <form method="POST" action="{{ route('admin.payroll-reject', $r->id) }}">
                @csrf
                <button class="mini" type="submit">Tolak</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Tidak ada draf yang menunggu keputusan.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="kepala" style="margin-top:28px">
  <h2>Riwayat disetujui</h2>
  <p>Buka kembali (reopen) bila admin cabang perlu mengubah potongan setelah approve — akses admin cabang kantor terkait terkunci total sampai dibuka kembali.</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kantor</th><th>Periode</th><th>Dibuat oleh</th><th>Jumlah Slip</th><th>Total (partial)</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($approvedRuns as $r)
        <tr>
          <td>{{ $r->office_name }}</td>
          <td class="angka">{{ date('F Y', strtotime($r->period)) }}</td>
          <td>{{ $r->maker_name }}</td>
          <td class="angka">{{ $r->jumlah_slip }}</td>
          <td class="angka">Rp{{ number_format($r->total_take_home_partial / 100, 0, ',', '.') }}</td>
          <td>
            <div class="aksi">
              <a href="{{ route('admin.payroll-run-detail', $r->id) }}" class="mini">Detail</a>
              <form method="POST" action="{{ route('admin.payroll-reopen', $r->id) }}">
                @csrf
                <button class="mini" type="submit"
                  data-confirm="Buka kembali payroll {{ $r->office_name }} periode {{ date('F Y', strtotime($r->period)) }}? Admin cabang akan bisa mengubah potongan lagi.">
                  Buka Kembali
                </button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Belum ada payroll yang disetujui.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection

@section('skrip')
<script>
var cekTinjau = document.getElementById('cek-tinjau-gaji');
if (cekTinjau) {
  cekTinjau.addEventListener('change', function () {
    document.getElementById('btn-generate').disabled = !this.checked;
  });
}
</script>
@endsection
