@extends('layouts.app')

@section('judul', 'Manajemen Aset')
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
.tag.tersedia{background:var(--hijau-muda);color:var(--hijau-tua)}
.tag.dipakai{background:var(--emas-muda);color:#7A5F0B}
.tag.perbaikan{background:var(--merah-muda);color:var(--merah)}
.tag.dihapuskan{background:var(--latar);color:var(--teks-lemah)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.pemegang{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.aksi-kecil{display:flex;gap:4px;flex-wrap:wrap;align-items:center}
@endsection

@section('isi')
<div class="kepala">
  <h2>Manajemen aset</h2>
  <p>Katalog aset perusahaan dan penugasannya ke pegawai — aset tidak dapat dihapus, hanya dihapuskan (status)</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('sysadmin.assets.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil">
      <label>Kode Aset</label>
      <input type="text" name="asset_code" required maxlength="30" placeholder="mis. LT-0001" style="width:110px">
    </div>
    <div class="bidang-kecil" style="flex:1;min-width:160px">
      <label>Nama</label>
      <input type="text" name="name" required maxlength="150" placeholder="mis. Laptop Dell Latitude 5420">
    </div>
    <div class="bidang-kecil">
      <label>Kategori</label>
      <input type="text" name="category" required maxlength="50" placeholder="mis. Laptop" style="width:130px">
    </div>
    <div class="bidang-kecil" style="min-width:140px">
      <label>Merek/Model</label>
      <input type="text" name="brand_model" maxlength="150">
    </div>
    <div class="bidang-kecil" style="min-width:130px">
      <label>No. Seri</label>
      <input type="text" name="serial_number" maxlength="100">
    </div>
    <div class="bidang-kecil">
      <label>Tgl. Beli</label>
      <input type="date" name="purchase_date">
    </div>
    <div class="bidang-kecil" style="min-width:150px">
      <label>Kantor</label>
      <select name="office_id" required>
        @foreach ($offices as $o)
          <option value="{{ $o->id }}">{{ $o->name }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="mini utama">Tambah Aset</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Kode</th><th>Nama</th><th>Kategori</th><th>Merek/Model</th><th>No. Seri</th>
        <th>Kondisi</th><th>Status</th><th>Kantor</th><th>Pemegang</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($assets as $a)
        @php $formId = 'aset-'.$a->id; @endphp
        <tr>
          <td class="angka">{{ $a->asset_code }}</td>
          <td><input form="{{ $formId }}" type="text" name="name" value="{{ $a->name }}" required maxlength="150"></td>
          <td><input form="{{ $formId }}" type="text" name="category" value="{{ $a->category }}" required maxlength="50"></td>
          <td><input form="{{ $formId }}" type="text" name="brand_model" value="{{ $a->brand_model }}" maxlength="150"></td>
          <td><input form="{{ $formId }}" type="text" name="serial_number" value="{{ $a->serial_number }}" maxlength="100"></td>
          <td>
            <select form="{{ $formId }}" name="condition" required>
              @foreach (['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'] as $val => $label)
                <option value="{{ $val }}" @selected($a->condition === $val)>{{ $label }}</option>
              @endforeach
            </select>
          </td>
          <td>
            @if ($a->status === 'dipakai')
              <span class="tag dipakai">Dipakai</span>
              <input type="hidden" form="{{ $formId }}" name="status" value="dipakai">
            @else
              <select form="{{ $formId }}" name="status" required>
                @foreach (['tersedia' => 'Tersedia', 'perbaikan' => 'Perbaikan', 'dihapuskan' => 'Dihapuskan'] as $val => $label)
                  <option value="{{ $val }}" @selected($a->status === $val)>{{ $label }}</option>
                @endforeach
              </select>
            @endif
          </td>
          <td>
            <select form="{{ $formId }}" name="office_id" required>
              @foreach ($offices as $o)
                <option value="{{ $o->id }}" @selected($a->office_id === $o->id)>{{ $o->name }}</option>
              @endforeach
            </select>
          </td>
          <td>
            @if ($a->holder_name)
              {{ $a->holder_name }}<div class="pemegang">{{ $a->holder_nrp }}</div>
            @else
              <span class="pemegang">—</span>
            @endif
          </td>
          <td>
            <div class="aksi-kecil">
              <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
              <form id="{{ $formId }}" method="POST" action="{{ route('sysadmin.assets.update', $a->id) }}" style="display:none">@csrf</form>

              @if ($a->status === 'tersedia')
                <details>
                  <summary class="mini" style="display:inline-block">Tugaskan</summary>
                  <form method="POST" action="{{ route('sysadmin.assets.assign', $a->id) }}" style="margin-top:6px;display:flex;gap:4px">
                    @csrf
                    <select name="employee_id" required style="min-width:160px">
                      @foreach ($employees as $e)
                        <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->nrp }})</option>
                      @endforeach
                    </select>
                    <button type="submit" class="mini utama">OK</button>
                  </form>
                </details>
              @elseif ($a->status === 'dipakai' && $a->assignment_id)
                <details>
                  <summary class="mini" style="display:inline-block">Kembalikan</summary>
                  <form method="POST" action="{{ route('sysadmin.assets.return', $a->assignment_id) }}" style="margin-top:6px;display:flex;gap:4px">
                    @csrf
                    <select name="returned_condition" required>
                      <option value="baik">Baik</option>
                      <option value="rusak_ringan">Rusak Ringan</option>
                      <option value="rusak_berat">Rusak Berat</option>
                    </select>
                    <button type="submit" class="mini utama">OK</button>
                  </form>
                </details>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="10" class="kosong">Belum ada aset.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
