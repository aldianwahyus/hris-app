@extends('layouts.app')

@section('judul', 'Template Checklist Onboarding')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.nonaktif{background:#EDEDED;color:#6B6B6B}
.mini{padding:5px 10px;border-radius:6px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Template Checklist Onboarding</h2>
    <p>Dipilih otomatis berdasarkan status kepegawaian saat pegawai baru disetujui</p>
  </div>
  <a href="{{ route('admin.onboarding-template-create') }}" class="btn">+ Buat Template</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Nama Template</th><th>Berlaku Untuk</th><th>Jumlah Item</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($templates as $t)
        <tr>
          <td>{{ $t->name }}</td>
          <td>{{ $t->employment_status_scope ? ($statusLabels[$t->employment_status_scope] ?? $t->employment_status_scope) : 'Semua Status' }}</td>
          <td class="angka">{{ $itemCounts[$t->id] ?? 0 }}</td>
          <td><span class="status {{ $t->is_active ? 'aktif' : 'nonaktif' }}">{{ $t->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
          <td>
            <form method="POST" action="{{ route('admin.onboarding-template-toggle', $t->id) }}">
              @csrf
              <button type="submit" class="mini">{{ $t->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Belum ada template. Pegawai baru yang disetujui tidak akan mendapat checklist onboarding sampai template dibuat.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
