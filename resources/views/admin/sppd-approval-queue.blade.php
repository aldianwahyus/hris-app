@extends('layouts.app')

@section('judul', 'Antrean Persetujuan SPPD')
@section('peran', 'Atasan Langsung / Pimpinan Kantor')

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
.aksi{display:flex;gap:6px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Antrean persetujuan SPPD</h2>
  <p>2 tahap untuk semua kategori: Atasan Langsung dulu, baru Pimpinan Kantor</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pegawai</th><th>Kantor</th><th>Tahap</th><th>Kategori</th><th>Tujuan</th>
        <th>Tanggal</th><th>Lumpsum</th><th>Plafon At-Cost</th><th>Tindakan</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">
            {{ $r->full_name }}
            <small>PG {{ $r->person_grade }}</small>
          </td>
          <td>{{ $r->office_name }}</td>
          <td>{{ $r->tahap }}</td>
          <td><span class="kategori-tag">{{ \App\Modules\Sppd\Domain\TripCategory::from($r->trip_category)->label() }}</span></td>
          <td>{{ $r->destination }}</td>
          <td class="angka">{{ date('j M', strtotime($r->start_date)) }}–{{ date('j M Y', strtotime($r->end_date)) }} ({{ $r->total_days }} hari)</td>
          <td class="angka">
            @if ($r->currency === 'USD')
              ${{ number_format(($r->uang_makan_cents + $r->uang_saku_cents) / 100, 2) }}
            @else
              Rp{{ number_format(($r->uang_makan_cents + $r->uang_saku_cents) / 100, 0, ',', '.') }}
            @endif
          </td>
          <td class="angka">
            @if ($r->estimasi_hotel_cents !== null)
              Rp{{ number_format(($r->estimasi_hotel_cents + $r->estimasi_angkutan_setempat_cents + $r->estimasi_transportasi_tujuan_cents) / 100, 0, ',', '.') }}
            @else
              —
            @endif
          </td>
          <td>
            @if (auth()->user()->hasRole('auditor'))
              <span style="font-size:11px;color:var(--teks-lemah)">Hanya-baca</span>
            @else
              <div class="aksi">
                <form method="POST" action="{{ route('admin.sppd-approve', $r->id) }}">
                  @csrf
                  <button class="mini utama" type="submit">Setujui</button>
                </form>
                <form method="POST" action="{{ route('admin.sppd-reject', $r->id) }}" onsubmit="mintaAlasanTolak(this, event); return false;">
                  @csrf
                  <button class="mini" type="submit">Tolak</button>
                </form>
              </div>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="kosong">Tidak ada pengajuan yang menunggu keputusan.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
