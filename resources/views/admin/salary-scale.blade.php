@extends('layouts.app')

@section('judul', 'Skala Imbalan Kerja')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);margin-top:16px}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
@endsection

@section('isi')
<div class="info">
  Menambah nilai baru untuk Person Grade + baris yang sama akan menutup nilai lama (bukan
  menimpanya) — riwayat tetap utuh untuk penghitungan ulang gaji historis.
</div>

<div class="kartu">
  <div class="kartu-judul">Tambah/ubah nilai skala imbalan kerja</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('sysadmin.salary-scale.store') }}"
    data-confirm="Nilai aktif untuk Person Grade dan baris ini akan ditutup dan digantikan. Lanjutkan?">
    @csrf
    <div class="baris-bidang">
      <div class="bidang">
        <label for="person_grade">Person Grade (1-19)</label>
        <input type="number" id="person_grade" name="person_grade" min="1" max="19" value="{{ old('person_grade') }}" required>
      </div>
      <div class="bidang">
        <label for="step">Baris</label>
        <input type="number" id="step" name="step" min="1" value="{{ old('step') }}" required>
      </div>
    </div>
    <div class="baris-bidang">
      <div class="bidang">
        <label for="amount">Imbalan Kerja (Rp)</label>
        <input type="number" id="amount" name="amount" min="0" step="1" value="{{ old('amount') }}" required>
      </div>
      <div class="bidang">
        <label for="effective_from">Berlaku Sejak</label>
        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from') }}" required>
      </div>
    </div>
    <div class="bidang">
      <label for="source_document">Dasar (nomor SK, opsional)</label>
      <input type="text" id="source_document" name="source_document" value="{{ old('source_document') }}">
    </div>
    <button type="submit" class="btn">Simpan</button>
  </form>
</div>

<div class="kepala">
  <h2>Nilai aktif hari ini</h2>
  <p>{{ $rows->count() }} kombinasi Person Grade × baris</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Person Grade</th><th>Baris</th><th>Imbalan Kerja</th><th>Berlaku Sejak</th><th>Dasar</th></tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="angka">{{ $r->person_grade }}</td>
          <td class="angka">{{ $r->step }}</td>
          <td class="angka">Rp{{ number_format($r->amount_cents / 100, 0, ',', '.') }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->effective_from)) }}</td>
          <td>{{ $r->source_document ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" style="text-align:center;color:var(--teks-lemah);padding:24px">Belum ada data.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
