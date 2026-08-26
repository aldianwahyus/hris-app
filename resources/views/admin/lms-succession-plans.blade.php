@extends('layouts.app')

@section('judul', 'Succession Planning')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.baris-tambah{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.bidang-kecil{display:flex;flex-direction:column;gap:5px}
.bidang-kecil label{font-size:11px;font-weight:600;color:var(--teks-lemah)}
.bidang-kecil select,.bidang-kecil input{padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.posisi{border:1px solid var(--garis);border-radius:var(--r);margin-bottom:14px;overflow:hidden}
.posisi-judul{padding:10px 14px;background:#FAFAF8;font-size:13.5px;font-weight:700}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:9px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:10px 12px;border-bottom:1px solid var(--garis);font-size:12px}
tbody tr:last-child td{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:20px;text-align:center;color:var(--teks-lemah);font-size:12.5px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Succession planning</h2>
  <p>Posisi kunci dan kandidat penerus (BRD §5.6)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.succession.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="min-width:180px">
      <label>Posisi Kunci</label>
      <select name="position_id" required>
        <option value="">— Pilih —</option>
        @foreach ($positions as $p)
          <option value="{{ $p->id }}">{{ $p->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil" style="min-width:180px">
      <label>Kandidat</label>
      <select name="candidate_employee_id" required>
        <option value="">— Pilih —</option>
        @foreach ($employees as $e)
          <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->nrp }})</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Kesiapan</label>
      <select name="readiness_level" required>
        <option value="ready_now">Siap Sekarang</option>
        <option value="ready_1_2_years">Siap 1-2 Tahun</option>
        <option value="ready_3_5_years">Siap 3-5 Tahun</option>
      </select>
    </div>
    <button type="submit" class="mini utama">Tambah Kandidat</button>
  </form>
</div>

@forelse ($plans as $positionId => $candidates)
  <div class="posisi">
    <div class="posisi-judul">{{ $candidates->first()->position_name }}</div>
    <table>
      <thead>
        <tr><th>Kandidat</th><th>Kesiapan</th><th>Catatan</th><th></th></tr>
      </thead>
      <tbody>
        @foreach ($candidates as $c)
          <tr>
            <td>{{ $c->candidate_name }}<br><small style="color:var(--teks-lemah)">{{ $c->candidate_nrp }}</small></td>
            <td><span class="tag">{{ ['ready_now' => 'Siap Sekarang', 'ready_1_2_years' => 'Siap 1-2 Tahun', 'ready_3_5_years' => 'Siap 3-5 Tahun'][$c->readiness_level] }}</span></td>
            <td>{{ $c->notes ?? '—' }}</td>
            <td>
              <form method="POST" action="{{ route('lms.admin.succession.destroy', $c->id) }}" data-confirm="Hapus kandidat ini dari daftar suksesi?">
                @csrf @method('DELETE')
                <button type="submit" class="mini">Hapus</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@empty
  <div class="kosong">Belum ada rencana suksesi.</div>
@endforelse
@endsection
