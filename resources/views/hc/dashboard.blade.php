@extends('layouts.app')

@section('judul', 'Dashboard HC')
@section('peran', 'HR Approver')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:11px;margin-bottom:16px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:14px}
.ring .a{font-size:25px;font-weight:800;letter-spacing:-.03em}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:13px}
@media(max-width:860px){.grid2{grid-template-columns:1fr}}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.baris{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--garis);font-size:12.5px}
.baris:last-child{border-bottom:0}
.laku{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.laku.rejected{background:var(--merah-muda);color:var(--merah)}
.laku.submitted{background:var(--emas-muda);color:#7A5F0B}
.kosong{padding:16px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
.ket{font-size:10.5px;color:var(--teks-lemah);margin-top:10px;line-height:1.6}
.baris-bar{margin-bottom:10px}
.baris-bar:last-child{margin-bottom:0}
.baris-bar-label{display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px}
.baris-bar-trek{background:var(--latar);border-radius:99px;height:8px;overflow:hidden}
.baris-bar-isi{height:100%;border-radius:99px;transition:.2s}
.gulir{overflow-x:auto}
table.aging{width:100%;border-collapse:collapse;margin-top:4px}
table.aging th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;color:var(--teks-lemah);padding:7px 8px;border-bottom:1px solid var(--garis)}
table.aging td{padding:7px 8px;font-size:12px;border-bottom:1px solid var(--garis)}
table.aging tr:last-child td{border-bottom:0}
.titik{display:inline-block;width:7px;height:7px;border-radius:99px;margin-right:5px}
.titik.aman{background:var(--hijau)}
.titik.hati{background:var(--emas)}
.titik.kritis{background:var(--merah)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Dashboard HC</h2>
  <p>Agregat seluruh bank (BANK_WIDE) — dashboard dasar, hari ini {{ now()->translatedFormat('d F Y') }}</p>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $totalHeadcount }}</div>
    <div class="l">Total pegawai</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $hadirHariIni }}</div>
    <div class="l">Hadir hari ini</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $telatHariIni }}</div>
    <div class="l">Terlambat hari ini</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $tanpaCatatanHariIni }}</div>
    <div class="l">Tanpa catatan hari ini</div>
  </div>
</div>

<div class="grid2">
  <div class="kartu">
    <div class="kartu-judul">Menunggu persetujuan</div>
    <div class="baris"><span>Cuti &amp; Izin</span><span class="angka">{{ $pendingApprovals['cuti'] }}</span></div>
    <div class="baris"><span>Lembur</span><span class="angka">{{ $pendingApprovals['lembur'] }}</span></div>
    <div class="baris"><span>SPPD</span><span class="angka">{{ $pendingApprovals['sppd'] }}</span></div>
    <div class="baris"><span>Payroll (draf)</span><span class="angka">{{ $pendingApprovals['payroll'] }}</span></div>
    <div class="baris"><span>Data Pegawai</span><span class="angka">{{ $pendingApprovals['data_pegawai'] }}</span></div>
    <div class="baris"><span>Tukar Shift</span><span class="angka">{{ $pendingApprovals['tukar_shift'] }}</span></div>
  </div>

  <div class="kartu">
    <div class="kartu-judul">Headcount per kantor</div>
    @forelse ($headcountByOffice as $h)
      <div class="baris"><span>{{ $h->office_name }}</span><span class="angka">{{ $h->jumlah }}</span></div>
    @empty
      <div class="kosong">Belum ada data pegawai.</div>
    @endforelse
  </div>
</div>

<div class="grid2">
  <div class="kartu">
    <div class="kartu-judul">Headcount per grade</div>
    @php $maxGrade = $headcountByGrade->max('jumlah') ?? 0; @endphp
    @forelse ($headcountByGrade as $g)
      @include('admin._bar-row', ['label' => 'PG '.$g->person_grade, 'value' => $g->jumlah, 'max' => $maxGrade])
    @empty
      <div class="kosong">Belum ada data pegawai.</div>
    @endforelse
  </div>

  <div class="kartu">
    <div class="kartu-judul">GAP formasi per kantor</div>
    @forelse ($gapByOffice as $o)
      @if ($o->authorized_headcount === null)
        <div class="baris"><span>{{ $o->office_name }}</span><span class="angka" style="color:var(--teks-lemah)">belum ditetapkan</span></div>
      @else
        @php $gap = $o->authorized_headcount - $o->actual_headcount; @endphp
        @include('admin._bar-row', [
          'label' => $o->office_name.' ('.$o->actual_headcount.'/'.$o->authorized_headcount.')',
          'value' => $o->actual_headcount,
          'max' => max($o->authorized_headcount, $o->actual_headcount),
          'color' => $gap < 0 ? 'var(--merah)' : 'var(--hijau-tua)',
        ])
      @endif
    @empty
      <div class="kosong">Belum ada data kantor.</div>
    @endforelse
    <p class="ket">Kelola formasi lewat <a href="{{ route('sysadmin.office-formasi.index') }}">Formasi Kantor</a>.</p>
  </div>
</div>

<div class="kartu">
  <div class="kartu-judul">Umur antrean menunggu persetujuan</div>
  <div class="gulir">
    <table class="aging">
      <thead>
        <tr><th>Antrean</th><th><span class="titik aman"></span>&lt; 3 hari</th><th><span class="titik hati"></span>3–13 hari</th><th><span class="titik kritis"></span>≥ 14 hari</th></tr>
      </thead>
      <tbody>
        @foreach (['cuti' => 'Cuti', 'lembur' => 'Lembur', 'sppd' => 'SPPD', 'data_pegawai' => 'Data Pegawai', 'tukar_shift' => 'Tukar Shift'] as $key => $label)
          <tr>
            <td>{{ $label }}</td>
            <td class="angka">{{ $pendingApprovalsAging[$key]['aman'] }}</td>
            <td class="angka">{{ $pendingApprovalsAging[$key]['hati'] }}</td>
            <td class="angka">{{ $pendingApprovalsAging[$key]['kritis'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="kartu">
  <div class="kartu-judul">Aktivitas terbaru</div>
  @forelse ($recentActivity as $a)
    <div class="baris">
      <div>
        <span class="laku {{ $a->action }}">{{ $a->action }}</span>
        <span style="margin-left:8px">{{ $a->auditable_type }}</span>
        @if ($a->actor_name)
          <span style="color:var(--teks-lemah)"> — {{ $a->actor_name }}</span>
        @endif
      </div>
      <span class="angka" style="color:var(--teks-lemah)">{{ date('j M Y H:i', strtotime($a->occurred_at)) }}</span>
    </div>
  @empty
    <div class="kosong">Belum ada aktivitas.</div>
  @endforelse
  <p class="ket">
    "Tanpa catatan hari ini" adalah proksi (total pegawai dikurangi hadir dan terlambat), sudah
    mengecualikan akhir pekan/hari libur nasional — TAPI belum mengecualikan pegawai yang sedang cuti/off individual.
  </p>
</div>
@endsection
