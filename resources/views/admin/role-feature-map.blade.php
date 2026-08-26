@extends('layouts.app')

@section('judul', 'Peta Peran')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
.tabel-bungkus{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table.matriks{border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap}
table.matriks th,table.matriks td{padding:7px 9px;border-bottom:1px solid var(--garis);text-align:center}
table.matriks th{background:#FAFAF8;font-weight:700;font-size:11px;position:sticky;top:0}
table.matriks td:first-child,table.matriks th:first-child{text-align:left;position:sticky;left:0;background:var(--putih);min-width:260px;white-space:normal}
table.matriks th:first-child{background:#FAFAF8;z-index:2}
.kategori-baris td{background:#F4F2EC;font-weight:700;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--teks-lemah);text-align:left;position:sticky;left:0}
.milik-sendiri{color:var(--teks-lemah);font-size:10px;font-weight:400;display:block;margin-top:2px}
.aksi{margin-top:16px;display:flex;gap:10px;align-items:center}
@endsection

@section('isi')
<div class="kepala">
  <h2>Peta peran</h2>
  <p>Centang izin yang dimiliki tiap peran — perubahan berlaku langsung tanpa deploy kode. Wewenang sesungguhnya ditegakkan oleh tabel ini (dibaca middleware rute); lingkup catatan per-transaksi (kantor sendiri/pohon kantor/bank-wide) tetap ditegakkan terpisah di masing-masing controller.</p>
</div>

<div class="info">
  @if ($actorIsSystemAdmin)
    Anda masuk sebagai Admin Sistem — dikecualikan dari pagar anti-eskalasi-hak, seluruh kolom
    termasuk peran Anda sendiri dapat diubah.
  @else
    Anda tidak dapat mengubah izin untuk peran yang sedang Anda pegang sendiri (pagar
    anti-eskalasi-hak) — kolom peran tersebut dinonaktifkan di bawah. Minta admin lain (atau
    Admin Sistem) untuk mengubahnya.
  @endif
  Dua area TIDAK ada di sini karena sengaja tetap terkunci di kode: manajemen
  pengguna (peran per akun) dan halaman ini sendiri — mencegah semua admin terkunci dari
  pengelolaan akses.
</div>

<form method="POST" action="{{ route('sysadmin.role-map.update') }}">
  @csrf

  <div class="tabel-bungkus">
    <table class="matriks">
      <thead>
        <tr>
          <th>Izin</th>
          @foreach ($roles as $role)
            <th>
              {{ $role->label() }}
              @if (in_array($role->value, $actorRoles, true))
                <span class="milik-sendiri">(peran Anda)</span>
              @endif
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($categories as $kategori)
          <tr class="kategori-baris">
            <td colspan="{{ count($roles) + 1 }}">{{ $kategori }}</td>
          </tr>
          @foreach ($permissionCatalog as $slug => [$label, $kategoriIzin])
            @continue($kategoriIzin !== $kategori)
            <tr>
              <td>{{ $label }}</td>
              @foreach ($roles as $role)
                <td>
                  <input
                    type="checkbox"
                    name="permissions[{{ $role->value }}][]"
                    value="{{ $slug }}"
                    @checked(in_array($slug, $grantedByRole[$role->value] ?? [], true))
                    @disabled(! $actorIsSystemAdmin && in_array($role->value, $actorRoles, true))
                  >
                </td>
              @endforeach
            </tr>
          @endforeach
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="aksi">
    <button type="submit" class="btn">Simpan Perubahan</button>
  </div>
</form>
@endsection
