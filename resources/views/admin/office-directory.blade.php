@extends('layouts.app')

@section('judul', 'Daftar Kantor')
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
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.tag.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.centang{display:flex;align-items:center;gap:4px;justify-content:center}
@endsection

@section('isi')
<div class="kepala">
  <h2>Daftar kantor</h2>
  <p>Rujukan tunggal kantor untuk seluruh modul (Data Pegawai, SK, Payroll, dst) — kantor tidak dapat dihapus, hanya dinonaktifkan</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.offices.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode</label>
      <input type="text" name="code" required maxlength="20" placeholder="mis. KC-BIMA" style="width:110px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:180px">
      <label>Nama</label>
      <input type="text" name="name" required maxlength="150" placeholder="mis. KC Bima">
    </div>
    <div class="bidang-kecil" style="flex:1.4;min-width:220px">
      <label>Alamat</label>
      <input type="text" name="address" maxlength="500" placeholder="mis. Jl. Sultan Hasanuddin No. 1, Bima">
    </div>
    <div class="bidang-kecil">
      <label>Tipe</label>
      <select name="office_type" required>
        <option value="head_office">Kantor Pusat</option>
        <option value="branch">Cabang (KC)</option>
        <option value="sub_branch">Cabang Pembantu (KCP)</option>
        <option value="functional">Fungsional</option>
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Kelas</label>
      <input type="text" name="office_class" maxlength="30" placeholder="mis. KC_1">
    </div>
    <div class="bidang-kecil" style="min-width:160px">
      <label>Kantor Induk</label>
      <select name="parent_office_id">
        <option value="">— Tidak ada —</option>
        @foreach ($activeOffices as $o)
          <option value="{{ $o->id }}">{{ $o->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Zona Waktu</label>
      <select name="timezone" required>
        <option value="Asia/Makassar">WITA</option>
        <option value="Asia/Jakarta">WIB</option>
        <option value="Asia/Jayapura">WIT</option>
      </select>
    </div>
    <button type="submit" class="mini utama">Tambah Kantor</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Kode</th><th>Nama</th><th>Alamat</th><th>Tipe</th><th>Kelas</th><th>Induk</th><th>Zona Waktu</th><th>Aktif</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($offices as $o)
        @php $formId = 'kantor-'.$o->id; @endphp
        <tr>
          <td class="angka"><input form="{{ $formId }}" type="text" name="code" value="{{ $o->code }}" required maxlength="20" style="width:100px"></td>
          <td><input form="{{ $formId }}" type="text" name="name" value="{{ $o->name }}" required maxlength="150"></td>
          <td style="min-width:220px"><input form="{{ $formId }}" type="text" name="address" value="{{ $o->address }}" maxlength="500"></td>
          <td>
            <select form="{{ $formId }}" name="office_type" required>
              @foreach (['head_office' => 'Kantor Pusat', 'branch' => 'KC', 'sub_branch' => 'KCP', 'functional' => 'Fungsional'] as $val => $label)
                <option value="{{ $val }}" @selected($o->office_type === $val)>{{ $label }}</option>
              @endforeach
            </select>
          </td>
          <td><input form="{{ $formId }}" type="text" name="office_class" value="{{ $o->office_class }}" maxlength="30"></td>
          <td>
            <select form="{{ $formId }}" name="parent_office_id">
              <option value="">—</option>
              @foreach ($activeOffices as $p)
                @continue($p->id === $o->id)
                <option value="{{ $p->id }}" @selected($o->parent_office_id === $p->id)>{{ $p->name }}</option>
              @endforeach
            </select>
          </td>
          <td>
            <select form="{{ $formId }}" name="timezone" required>
              @foreach (['Asia/Makassar' => 'WITA', 'Asia/Jakarta' => 'WIB', 'Asia/Jayapura' => 'WIT'] as $val => $label)
                <option value="{{ $val }}" @selected($o->timezone === $val)>{{ $label }}</option>
              @endforeach
            </select>
          </td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($o->is_active)>
          </td>
          <td>
            <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
            {{-- form kosong (di luar <table>, valid HTML) yang jadi tujuan seluruh input
                 baris ini lewat atribut form="..." — trik standar untuk kolom form dalam tabel. --}}
            <form id="{{ $formId }}" method="POST" action="{{ route('sysadmin.offices.update', $o->id) }}" style="display:none">@csrf</form>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="kosong">Belum ada kantor.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
