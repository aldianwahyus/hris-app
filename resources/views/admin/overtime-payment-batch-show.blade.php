@extends('layouts.app')

@section('judul', 'Batch Pembayaran Lembur')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.metrik{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px}
.metrik .angka-besar{font-size:22px;font-weight:700}
.metrik .label{font-size:11px;color:var(--teks-lemah);margin-top:4px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.kartu h3{font-size:13px;font-weight:700;margin-bottom:10px}
.baris{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.aksi{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.mini{padding:8px 16px;border-radius:7px;font-family:inherit;font-size:12.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none;color:inherit}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:9px 10px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:9px 10px;border-bottom:1px solid var(--garis);font-size:12px}
tbody tr:last-child td{border-bottom:0}

/* Reset global *{margin:0} pada layout menghapus margin:auto bawaan
   browser yang biasanya menengahkan <dialog>, jadi posisi tengah
   dipaksa eksplisit di sini (bukan mengandalkan default UA). */
.modal-cetak{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);margin:0;
  border:none;border-radius:var(--r);padding:0;width:min(900px,92vw);height:88vh;max-height:88vh}
.modal-cetak::backdrop{background:rgba(15,31,26,.55)}
.modal-cetak .modal-kepala{display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding:12px 16px;border-bottom:1px solid var(--garis)}
.modal-cetak .modal-kepala h3{font-size:13.5px;font-weight:700}
.modal-cetak .modal-tutup{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:12px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.modal-cetak .modal-tutup:hover{background:var(--latar)}
.modal-cetak iframe{display:block;width:100%;height:calc(88vh - 49px);border:0}
@endsection

@section('isi')
<div class="kepala">
  <h2>Batch {{ $batch->spkl_number }}</h2>
  <p>
    {{ $batch->payer_scope === 'hc' ? 'Kantor Pusat — '.$batch->division : $batch->office_name }}
    · Pejabat pengusul: {{ $batch->signatory_name }} · Tarif pajak: {{ $batch->tax_rate_percent }}%
  </p>
</div>

@if (session('sukses'))
  <div class="pesan sukses">{{ session('sukses') }}</div>
@endif

<div class="grid">
  <div class="metrik">
    <div class="angka-besar">Rp {{ number_format($batch->total_gross_cents / 100, 0, ',', '.') }}</div>
    <div class="label">Total Bruto</div>
  </div>
  <div class="metrik">
    <div class="angka-besar">Rp {{ number_format($batch->total_tax_cents / 100, 0, ',', '.') }}</div>
    <div class="label">Total Pajak</div>
  </div>
  <div class="metrik">
    <div class="angka-besar">Rp {{ number_format($batch->total_net_cents / 100, 0, ',', '.') }}</div>
    <div class="label">Total Bersih (Masuk Rekening)</div>
  </div>
</div>

<div class="aksi">
  <button type="button" class="mini utama" onclick="bukaCetak('{{ route('admin.overtime-payment-batch.print-memo', $batch->id) }}', 'Memo Internal')">Cetak Memo Internal</button>
  <button type="button" class="mini utama" onclick="bukaCetak('{{ route('admin.overtime-payment-batch.print-nota-debet', $batch->id) }}', 'Nota Debet')">Cetak Nota Debet</button>
  <button type="button" class="mini utama" onclick="bukaCetak('{{ route('admin.overtime-payment-batch.print-jurnal-slip', $batch->id) }}', 'Jurnal Slip Pajak')">Cetak Jurnal Slip Pajak</button>
  <button type="button" class="mini utama" onclick="bukaCetak('{{ route('admin.overtime-payment-batch.print-lampiran-penerima', $batch->id) }}', 'Lampiran Penerima')">Cetak Lampiran Penerima</button>
</div>

<dialog id="modal-cetak" class="modal-cetak">
  <div class="modal-kepala">
    <h3 id="modal-cetak-judul">Pratinjau Dokumen</h3>
    <button type="button" class="modal-tutup" onclick="document.getElementById('modal-cetak').close()">✕ Tutup</button>
  </div>
  <iframe id="modal-cetak-iframe" src="about:blank" title="Pratinjau dokumen cetak"></iframe>
</dialog>

<div class="kartu">
  <h3>Akun jurnal</h3>
  <div class="baris"><span>Akun Beban</span><span>{{ $batch->expense_account_code }} — {{ $batch->expense_account_name }}</span></div>
  <div class="baris"><span>Akun Penampungan Pajak</span><span>{{ $batch->tax_account_code }} — {{ $batch->tax_account_name }}</span></div>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>No. Rekening</th><th>Bruto</th><th>Pajak</th><th>Bersih</th></tr>
    </thead>
    <tbody>
      @foreach ($items as $i)
        <tr>
          <td>{{ $i->full_name }}<br><small style="color:var(--teks-lemah)">{{ $i->nrp }}</small></td>
          <td class="angka">{{ $i->bank_account_number ?? '—' }}</td>
          <td class="angka">Rp {{ number_format($i->gross_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp {{ number_format($i->tax_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">Rp {{ number_format($i->net_cents / 100, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection

@section('skrip')
<script>
  function bukaCetak(url, judul) {
    document.getElementById('modal-cetak-judul').textContent = 'Pratinjau — ' + judul;
    document.getElementById('modal-cetak-iframe').src = url;
    document.getElementById('modal-cetak').showModal();
  }

  document.getElementById('modal-cetak').addEventListener('close', () => {
    document.getElementById('modal-cetak-iframe').src = 'about:blank';
  });
</script>
@endsection
