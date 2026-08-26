@extends('layouts.app')

@section('judul', 'Persetujuan Data Pegawai')
@section('peran', 'HR Approver')

@section('gaya')
.rahasia{background:var(--hijau-tua);color:#fff;border-radius:var(--r);padding:14px 16px;margin-bottom:15px}
.rahasia .l{font-size:11px;opacity:.75;margin-bottom:4px}
.rahasia .p{font-family:'JetBrains Mono',monospace;font-size:16px;font-weight:700;letter-spacing:.03em}
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:top}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.diff{font-size:11.5px;line-height:1.7}
.diff .f{font-weight:600}
.diff .lama{color:var(--merah);text-decoration:line-through;margin-right:6px}
.diff .baru{color:var(--hijau-tua);font-weight:700}
.aksi{display:flex;gap:6px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
@if (session('kata_sandi_baru'))
  <div class="rahasia">
    <div class="l">Kata sandi baru untuk {{ session('kata_sandi_untuk') }} — hanya ditampilkan sekali, salin/sampaikan sekarang:</div>
    <div class="p">{{ session('kata_sandi_baru') }}</div>
  </div>
@endif

<div class="kepala">
  <h2>Persetujuan pegawai baru</h2>
  <p>Pengajuan dari SYSADMIN (BANK_WIDE) — menyetujui akan membuat baris data pegawai + akun login baru</p>
</div>

<div class="gulir" style="margin-bottom:24px">
  <table>
    <thead>
      <tr><th>NRP</th><th>Nama</th><th>Diajukan oleh</th><th>Tanggal</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($newEmployeeRows as $r)
        <tr>
          <td class="angka">{{ $r->proposed_data['nrp'] }}</td>
          <td>{{ $r->proposed_data['full_name'] }}</td>
          <td>{{ $r->maker_name }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td>
            <div class="aksi">
              <form method="POST" action="{{ route('admin.new-employee-approve', $r->id) }}">
                @csrf
                <button class="mini utama" type="submit">Setujui</button>
              </form>
              <form method="POST" action="{{ route('admin.new-employee-reject', $r->id) }}">
                @csrf
                <button class="mini" type="submit">Tolak</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Tidak ada pengajuan pegawai baru yang menunggu.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="kepala">
  <h2>Persetujuan perubahan data pegawai</h2>
  <p>Pengajuan dari hr_admin/SYSADMIN seluruh kantor (BANK_WIDE)</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Pegawai</th><th>Perubahan diajukan</th><th>Diajukan oleh</th><th>Tanggal</th><th>Tindakan</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="peg">
            {{ $r->full_name }}
            <small>{{ $r->nrp }} · {{ $r->office_name }}</small>
          </td>
          <td class="diff">
            @foreach ($r->proposed_changes as $field => $newValue)
              <div>
                <span class="f">{{ $field }}</span>:
                <span class="lama">{{ $r->previous_values[$field] ?? '—' }}</span>
                <span class="baru">{{ $newValue }}</span>
              </div>
            @endforeach
          </td>
          <td>{{ $r->maker_name }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td>
            <div class="aksi">
              <form method="POST" action="{{ route('admin.employee-approve', $r->id) }}">
                @csrf
                <button class="mini utama" type="submit">Setujui</button>
              </form>
              <form method="POST" action="{{ route('admin.employee-reject', $r->id) }}">
                @csrf
                <button class="mini" type="submit">Tolak</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Tidak ada pengajuan yang menunggu keputusan.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
