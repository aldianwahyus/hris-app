@extends('layouts.app')

@section('judul', 'Detail SPPD Massal')
@section('peran', 'Admin HC / Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
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
  <h2>SPPD Massal {{ $group->group_number }}</h2>
  <p>Memo {{ $group->memo_number }} · {{ $group->destination }} · {{ date('j M Y', strtotime($group->start_date)) }} – {{ date('j M Y', strtotime($group->end_date)) }}</p>
</div>

@if (session('sukses'))
  <div class="pesan sukses">{{ session('sukses') }}</div>
@endif

<div class="aksi">
  <button type="button" class="mini utama" onclick="bukaCetak('{{ route('sppd-memo.print-surat-jalan', $group->id) }}', 'Surat Jalan')">Cetak Surat Jalan</button>
</div>

<div class="kartu">
  <h3>Pegawai yang Berangkat</h3>
  <div class="baris"><span>Kategori Perjalanan</span><span>{{ \App\Modules\Sppd\Domain\TripCategory::from($group->trip_category)->label() }}</span></div>
  <div class="baris"><span>Keperluan</span><span>{{ $group->purpose }}</span></div>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Status</th><th>Total Anggaran</th><th></th></tr>
    </thead>
    <tbody>
      @foreach ($travelers as $t)
        @php
          $total = $t->uang_makan_cents + $t->uang_saku_cents + ($t->estimasi_hotel_cents ?? 0)
            + ($t->hotel_kompensasi_cents ?? 0) + ($t->estimasi_angkutan_setempat_cents ?? 0) + ($t->estimasi_transportasi_tujuan_cents ?? 0)
            + ($t->uang_makan_h1_cents ?? 0) + ($t->uang_saku_h1_cents ?? 0) + ($t->uang_makan_konsumsi_cents ?? 0);
        @endphp
        <tr>
          <td>{{ $t->full_name }}<br><small style="color:var(--teks-lemah)">{{ $t->nrp }}</small></td>
          <td>{{ $t->status }}</td>
          <td class="angka">Rp {{ number_format($total / 100, 0, ',', '.') }}</td>
          <td>
            <button type="button" class="mini" onclick="bukaCetak('{{ route('sppd-memo.print-rincian-lumpsum', [$group->id, $t->id]) }}', {{ \Illuminate\Support\Js::from('Rincian Lumpsum — '.$t->full_name) }})">Cetak Rincian Lumpsum</button>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<dialog id="modal-cetak" class="modal-cetak">
  <div class="modal-kepala">
    <h3 id="modal-cetak-judul">Pratinjau Dokumen</h3>
    <button type="button" class="modal-tutup" onclick="document.getElementById('modal-cetak').close()">✕ Tutup</button>
  </div>
  <iframe id="modal-cetak-iframe" src="about:blank" title="Pratinjau dokumen cetak"></iframe>
</dialog>
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
