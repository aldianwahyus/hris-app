@extends('layouts.app')

@section('judul', 'Daftar Jabatan')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil input,.bidang-kecil select{padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:9px 10px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:8px 10px;border-bottom:1px solid var(--garis);font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody input,tbody select{padding:5px 7px;border:1px solid var(--garis);border-radius:6px;
  font-family:inherit;font-size:11.5px;width:100%}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.centang{display:flex;align-items:center;gap:4px;justify-content:center}
.grade{display:flex;gap:4px;align-items:center}
.grade input{width:44px !important}
@endsection

@section('isi')
<div class="kepala">
  <h2>Daftar jabatan</h2>
  <p>Rujukan tunggal jabatan untuk seluruh modul (Data Pegawai, SK, Lembur, dst) — jabatan tidak dapat dihapus, hanya dinonaktifkan</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.positions.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode</label>
      <input type="text" name="code" required maxlength="40" placeholder="mis. TELLER" style="width:120px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:180px">
      <label>Nama</label>
      <input type="text" name="name" required maxlength="150" placeholder="mis. Teller">
    </div>
    <div class="bidang-kecil">
      <label>Klasifikasi</label>
      <select name="classification">
        <option value="">—</option>
        <option value="business">Bisnis</option>
        <option value="support">Support</option>
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Grade (min–maks)</label>
      <div class="grade">
        <input type="number" name="job_grade_min" min="1" max="255">
        <span>–</span>
        <input type="number" name="job_grade_max" min="1" max="255">
      </div>
    </div>
    <div class="bidang-kecil">
      <label>Kelas Tarif Lembur</label>
      <select name="overtime_rate_class">
        <option value="">—</option>
        <option value="MGR_SPV_OFC">MGR/SPV/OFC</option>
        <option value="ASST_ADM">ASST/ADM</option>
        <option value="NON_ADMIN">NON_ADMIN</option>
      </select>
    </div>
    <button type="submit" class="mini utama">Tambah Jabatan</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Kode</th><th>Nama</th><th>Klasifikasi</th><th>Grade</th><th>Lembur Reguler</th>
        <th>Lembur Crash</th><th>Kelas Tarif</th><th>Aktif</th><th></th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($positions as $p)
        @php $formId = 'jabatan-'.$p->id; @endphp
        <tr>
          <td class="angka">{{ $p->code }}</td>
          <td><input form="{{ $formId }}" type="text" name="name" value="{{ $p->name }}" required maxlength="150"></td>
          <td>
            <select form="{{ $formId }}" name="classification">
              <option value="">—</option>
              <option value="business" @selected($p->classification === 'business')>Bisnis</option>
              <option value="support" @selected($p->classification === 'support')>Support</option>
            </select>
          </td>
          <td>
            <div class="grade">
              <input form="{{ $formId }}" type="number" name="job_grade_min" value="{{ $p->job_grade_min }}" min="1" max="255">
              <span>–</span>
              <input form="{{ $formId }}" type="number" name="job_grade_max" value="{{ $p->job_grade_max }}" min="1" max="255">
            </div>
          </td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="eligible_overtime_regular" value="1" @checked($p->eligible_overtime_regular)>
          </td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="eligible_overtime_crash" value="1" @checked($p->eligible_overtime_crash)>
          </td>
          <td>
            <select form="{{ $formId }}" name="overtime_rate_class">
              <option value="">—</option>
              @foreach (['MGR_SPV_OFC', 'ASST_ADM', 'NON_ADMIN'] as $rc)
                <option value="{{ $rc }}" @selected($p->overtime_rate_class === $rc)>{{ $rc }}</option>
              @endforeach
            </select>
          </td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($p->is_active)>
          </td>
          <td>
            <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
            <form id="{{ $formId }}" method="POST" action="{{ route('sysadmin.positions.update', $p->id) }}" style="display:none">@csrf</form>
          </td>
          <td><a href="{{ route('lms.admin.competencies.map-position', $p->id) }}" class="mini">Kompetensi</a></td>
        </tr>
      @empty
        <tr><td colspan="10" class="kosong">Belum ada jabatan.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
