@extends('layouts.app')

@section('judul', 'Konfigurasi Parameter Sistem')
@section('peran', 'Admin Sistem (IT)')

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
.kode{font-weight:700}
.modul-tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;
  border-radius:99px;background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tanpa-nilai{color:var(--merah);font-size:11px;font-weight:600}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
@endsection

@section('isi')
<div class="info">
  Nilai lama TIDAK PERNAH ditimpa — mengganti nilai berarti menutup yang lama dan menambah
  yang baru mulai tanggal tertentu, sehingga penghitungan historis tetap dapat diverifikasi.
  Perubahan tercatat penuh di Log Audit.
</div>

<div class="kepala">
  <h2>Konfigurasi parameter sistem</h2>
  <p>Seluruh parameter kebijakan berversi (tarif, batas, persentase) · nilai berlaku hari ini</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama</th><th>Modul</th><th>Nilai Saat Ini</th><th>Berlaku Sejak</th><th>Dasar</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @foreach ($parameters as $p)
        <tr>
          <td class="kode angka">{{ $p->code }}</td>
          <td>{{ $p->name }}</td>
          <td><span class="modul-tag">{{ $p->owner_module }}</span></td>
          <td class="angka">
            @if ($p->nilai_saat_ini !== null)
              {{ $p->nilai_saat_ini }} {{ $p->unit }}
            @else
              <span class="tanpa-nilai">Tidak ada nilai aktif</span>
            @endif
          </td>
          <td class="angka">{{ $p->effective_from ? date('j M Y', strtotime($p->effective_from)) : '—' }}</td>
          <td>{{ $p->source_document ?? '—' }}</td>
          <td>
            <a class="mini" href="{{ route('sysadmin.parameters.history', $p->id) }}">Riwayat &amp; Ubah</a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
