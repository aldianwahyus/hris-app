@extends('layouts.app')

@section('judul', 'Pencairan SPPD')
@section('peran', $lingkup === 'Seluruh Bank' ? 'Admin HC' : 'Admin Cabang')

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
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.kategori-tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;
  border-radius:99px;background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.aksi{display:flex;gap:6px;align-items:center}
.aksi input[type=text]{width:150px;padding:6px 9px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:11.5px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Pencairan SPPD — {{ $lingkup }}</h2>
  <p>SPPD yang sudah disetujui, menunggu pencairan dana. HCIS mencatat nomor referensi transfer — pencairan sesungguhnya dieksekusi di Core Banking/Treasury.</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pegawai</th><th>Kategori</th><th>Tujuan</th><th>Disetujui Oleh</th>
        <th>Total Anggaran</th><th>Tindakan</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">
            {{ $r->full_name }}
            <small>{{ $r->request_number }}</small>
          </td>
          <td><span class="kategori-tag">{{ \App\Modules\Sppd\Domain\TripCategory::from($r->trip_category)->label() }}</span></td>
          <td>{{ $r->destination }}</td>
          <td>{{ $r->approver_name }}</td>
          <td class="angka">
            @php
              $totalCents = $r->uang_makan_cents + $r->uang_saku_cents
                + ($r->estimasi_hotel_cents ?? 0) + ($r->estimasi_angkutan_setempat_cents ?? 0)
                + ($r->estimasi_transportasi_tujuan_cents ?? 0);
            @endphp
            @if ($r->currency === 'USD')
              ${{ number_format($totalCents / 100, 2) }}
            @else
              Rp{{ number_format($totalCents / 100, 0, ',', '.') }}
            @endif
          </td>
          <td>
            @if (auth()->user()->hasRole('auditor'))
              <span style="font-size:11px;color:var(--teks-lemah)">Hanya-baca</span>
            @else
              <form method="POST" action="{{ route($lingkup === 'Seluruh Bank' ? 'admin.sppd-disburse' : 'hr.sppd-disbursement.disburse', $r->id) }}" class="aksi">
                @csrf
                <input type="text" name="disbursement_reference" placeholder="No. referensi transfer" required maxlength="100">
                <button class="mini utama" type="submit">Cairkan</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Tidak ada SPPD yang menunggu pencairan.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
