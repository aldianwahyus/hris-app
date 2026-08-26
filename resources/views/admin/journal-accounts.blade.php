@extends('layouts.app')

@section('judul', 'Daftar Akun Jurnal')
@section('peran', 'Admin HC / Admin Sistem')

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
tbody input,tbody select{padding:5px 7px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:11.5px;width:100%}
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
  <h2>Daftar akun jurnal</h2>
  <p>Akun beban &amp; penampungan pajak untuk pembayaran lembur massal — tidak dapat dihapus, hanya dinonaktifkan</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.journal-accounts.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode</label>
      <input type="text" name="code" required maxlength="30" placeholder="mis. IA-BEBAN-LEMBUR" style="width:160px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:220px">
      <label>Nama Akun</label>
      <input type="text" name="name" required maxlength="150" placeholder="mis. IA Beban Uang Lembur">
    </div>
    <div class="bidang-kecil">
      <label>Kategori</label>
      <select name="category" required>
        <option value="beban">Beban</option>
        <option value="penampungan_pajak">Penampungan Pajak</option>
      </select>
    </div>
    <button type="submit" class="mini utama">Tambah Akun</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Aktif</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($accounts as $a)
        @php $formId = 'akun-'.$a->id; @endphp
        <tr>
          <td class="angka">{{ $a->code }}</td>
          <td><input form="{{ $formId }}" type="text" name="name" value="{{ $a->name }}" required maxlength="150"></td>
          <td>
            <select form="{{ $formId }}" name="category" required>
              <option value="beban" @selected($a->category === 'beban')>Beban</option>
              <option value="penampungan_pajak" @selected($a->category === 'penampungan_pajak')>Penampungan Pajak</option>
            </select>
          </td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($a->is_active)>
          </td>
          <td>
            <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
            <form id="{{ $formId }}" method="POST" action="{{ route('sysadmin.journal-accounts.update', $a->id) }}" style="display:none">@csrf</form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada akun jurnal.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
