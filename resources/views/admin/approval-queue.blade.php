@extends('layouts.app')

@section('judul', 'Antrean Persetujuan')
@section('peran', 'Direktur Pembina · Wilayah Cabang')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;
  margin-bottom:16px;flex-wrap:wrap;gap:12px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:11px;margin-bottom:16px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:14px}
.ring.bahaya{border-color:#F3C6C2;background:var(--merah-muda)}
.ring .a{font-size:25px;font-weight:800;letter-spacing:-.03em}
.ring.bahaya .a{color:var(--merah)}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}

.peringatan{display:flex;gap:11px;align-items:flex-start;background:var(--merah-muda);
  border:1px solid #F3C6C2;border-radius:10px;padding:13px 15px;margin-bottom:16px}
.peringatan .t{font-size:12.5px;font-weight:700;color:var(--merah)}
.peringatan .d{font-size:12px;color:#7A2620;margin-top:3px;line-height:1.6}

.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#FAFCFB}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.sisa{display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:12px;white-space:nowrap}
.titik{width:6px;height:6px;border-radius:99px;flex-shrink:0}
.titik.aman{background:var(--hijau)}
.titik.hati{background:var(--emas)}
.titik.kritis{background:var(--merah)}
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
  <div>
    <h2>Antrean persetujuan lembur</h2>
    <p>Pengajuan dari seluruh kantor cabang dan cabang pembantu</p>
  </div>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $summary['menunggu'] }}</div>
    <div class="l">Menunggu keputusan</div>
  </div>
  <div class="ring {{ $summary['kritis'] > 0 ? 'bahaya' : '' }}">
    <div class="a angka">{{ $summary['kritis'] }}</div>
    <div class="l">Batas waktu ≤ 3 hari</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ rtrim(rtrim(number_format($summary['jam'], 1, ',', '.'), '0'), ',') }}</div>
    <div class="l">Jam lembur menunggu</div>
  </div>
  <div class="ring">
    <div class="a angka">Rp{{ number_format($summary['nilai'] / 100, 0, ',', '.') }}</div>
    <div class="l">Nilai menunggu persetujuan</div>
  </div>
</div>

@if ($summary['kritis'] > 0)
  <div class="peringatan">
    <div style="font-size:16px">⚠</div>
    <div>
      <div class="t">{{ $summary['kritis'] }} pengajuan mendekati batas waktu</div>
      <div class="d">
        Lembur yang belum disetujui dalam 30 hari sejak pelaksanaan tidak dapat dibayarkan.
        Pegawai berikut sudah bekerja dan menunggu keputusan.
      </div>
    </div>
  </div>
@endif

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pegawai</th><th>Kantor</th><th>Tanggal kerja</th><th>Jenis</th>
        <th>Jam</th><th>Nilai</th><th>Batas waktu</th><th>Tindakan</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">
            {{ $r->full_name }}
            <small>{{ $r->position_name }} · PG {{ $r->person_grade }}</small>
          </td>
          <td>{{ $r->office_name }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->work_date)) }}</td>
          <td>{{ $r->overtime_type === 'crash_program' ? 'Crash program' : 'Lembur biasa' }}</td>
          <td class="angka">
            {{ $r->overtime_type === 'crash_program'
                ? '1 hari'
                : rtrim(rtrim(number_format($r->payable_hours, 1, ',', '.'), '0'), ',') }}
          </td>
          <td class="angka">Rp{{ number_format($r->amount_cents / 100, 0, ',', '.') }}</td>
          <td>
            <span class="sisa">
              <span class="titik {{ $r->urgency }}"></span>
              {{ $r->remaining_days > 0 ? $r->remaining_days . ' hari' : 'Lewat' }}
            </span>
          </td>
          <td>
            @if (auth()->user()->hasRole('auditor'))
              <span style="font-size:11px;color:var(--teks-lemah)">Hanya-baca</span>
            @else
              <div class="aksi">
                <form method="POST" action="{{ route('admin.approve', $r->id) }}">
                  @csrf
                  <button class="mini utama" type="submit">Setujui</button>
                </form>
                <form method="POST" action="{{ route('admin.reject', $r->id) }}">
                  @csrf
                  <button class="mini" type="submit">Tolak</button>
                </form>
              </div>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="kosong">
            Tidak ada pengajuan yang menunggu keputusan.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
