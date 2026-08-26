@extends('layouts.app')

@section('judul', 'Formasi Kantor')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:8px 12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.gap{font-weight:700}
.gap.kurang{color:var(--merah)}
.gap.cukup{color:var(--hijau-tua)}
.baris-form{display:flex;gap:6px;align-items:center}
.baris-form input{width:90px;padding:6px 8px;border:1px solid var(--garis);border-radius:6px;
  font-family:inherit;font-size:12px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}

.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
  color:var(--teks-lemah);margin-bottom:14px;display:flex;justify-content:space-between;align-items:center}
.legenda{display:flex;gap:14px;font-weight:500;text-transform:none;letter-spacing:0}
.legenda span{display:inline-flex;align-items:center;gap:5px}
.legenda i{width:9px;height:9px;border-radius:2px;display:inline-block}
.legenda i.aktual{background:var(--hijau)}
.legenda i.formasi{background:var(--garis);border:1px solid var(--teks-lemah)}
.baris-grafik{margin-bottom:14px}
.baris-grafik:last-child{margin-bottom:0}
.baris-grafik-label{display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px}
.baris-grafik-label .angka{color:var(--teks-lemah)}
.baris-grafik-trek{position:relative;background:var(--latar);border-radius:6px;height:16px;overflow:hidden}
.baris-grafik-formasi{position:absolute;top:0;bottom:0;border-right:2px dashed var(--teks-lemah)}
.baris-grafik-aktual{position:absolute;top:0;bottom:0;left:0;border-radius:6px;background:var(--hijau);transition:.2s}
.baris-grafik-aktual.kurang{background:var(--merah)}
@endsection

@section('isi')
<div class="kepala">
  <h2>Formasi kantor</h2>
  <p>Kuota pegawai resmi per kantor — dasar perhitungan GAP di Analitik SDM. Kosongkan bila belum ditetapkan.</p>
</div>

@if ($errors->any())
  <div class="pesan gagal" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif

@php $ditetapkan = $offices->whereNotNull('authorized_headcount'); @endphp
<div class="kartu">
  <div class="kartu-judul">
    <span>Aktual vs Formasi</span>
    <span class="legenda">
      <span><i class="aktual"></i> Aktual</span>
      <span><i class="formasi"></i> Garis formasi</span>
    </span>
  </div>
  @forelse ($ditetapkan as $o)
    @php
      $skala = max($o->authorized_headcount, $o->actual_headcount, 1);
      $persenAktual = min(100, ($o->actual_headcount / $skala) * 100);
      $persenFormasi = min(100, ($o->authorized_headcount / $skala) * 100);
      $kurang = $o->actual_headcount < $o->authorized_headcount;
    @endphp
    <div class="baris-grafik">
      <div class="baris-grafik-label">
        <span>{{ $o->name }}</span>
        <span class="angka">{{ $o->actual_headcount }} / {{ $o->authorized_headcount }}</span>
      </div>
      <div class="baris-grafik-trek">
        <div class="baris-grafik-aktual {{ $kurang ? 'kurang' : '' }}" style="width:{{ $persenAktual }}%"></div>
        <div class="baris-grafik-formasi" style="left:{{ $persenFormasi }}%"></div>
      </div>
    </div>
  @empty
    <div style="text-align:center;color:var(--teks-lemah);padding:16px;font-size:12.5px">
      Belum ada kantor dengan formasi yang ditetapkan.
    </div>
  @endforelse
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama Kantor</th><th>Aktual</th><th>Formasi</th><th>GAP</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($offices as $o)
        <tr>
          <td class="angka">{{ $o->code }}</td>
          <td class="peg">{{ $o->name }}</td>
          <td class="angka">{{ $o->actual_headcount }}</td>
          <td class="angka">{{ $o->authorized_headcount ?? '— belum ditetapkan' }}</td>
          <td class="angka">
            @if ($o->authorized_headcount === null)
              —
            @else
              @php $gap = $o->authorized_headcount - $o->actual_headcount; @endphp
              <span class="gap {{ $gap < 0 ? 'kurang' : 'cukup' }}">{{ $gap > 0 ? '+' : '' }}{{ $gap }}</span>
            @endif
          </td>
          <td>
            <form method="POST" action="{{ route('sysadmin.office-formasi.update', $o->id) }}" class="baris-form">
              @csrf
              <input type="number" min="0" name="authorized_headcount" value="{{ $o->authorized_headcount }}" placeholder="Kuota" required>
              <button type="submit" class="mini utama">Simpan</button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" style="text-align:center;color:var(--teks-lemah);padding:24px">Belum ada data kantor.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
