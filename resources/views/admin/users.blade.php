@extends('layouts.app')

@section('judul', 'Manajemen Pengguna')
@section('peran', 'Admin Sistem (IT)')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peran-tag{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:2px 4px 2px 8px;
  border-radius:99px;margin:1px 3px 1px 0;background:var(--hijau-muda);color:var(--hijau-tua)}
.peran-tag button{border:none;background:rgba(6,78,59,.14);color:inherit;border-radius:99px;
  width:14px;height:14px;font-size:9px;line-height:1;cursor:pointer;padding:0}
.peran-tag button:hover{background:rgba(6,78,59,.28)}
.tindakan-kolom{display:flex;flex-direction:column;gap:6px;min-width:190px}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);transition:.12s}
.mini:hover{background:var(--latar)}
.beri-peran{display:flex;gap:5px}
.beri-peran select{flex:1;padding:5px 6px;border:1px solid var(--garis);border-radius:6px;
  font-size:11px;font-family:inherit}
.diri-sendiri{font-size:11px;color:var(--teks-lemah);font-style:italic}
.rahasia{background:var(--hijau-tua);color:#fff;border-radius:var(--r);padding:14px 16px;margin-bottom:15px}
.rahasia .l{font-size:11px;opacity:.75;margin-bottom:4px}
.rahasia .p{font-family:'JetBrains Mono',monospace;font-size:16px;font-weight:700;letter-spacing:.03em}
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
@endsection

@section('isi')
@if (session('kata_sandi_baru'))
  <div class="rahasia">
    <div class="l">Kata sandi baru untuk {{ session('kata_sandi_untuk') }} — hanya ditampilkan sekali, salin sekarang:</div>
    <div class="p">{{ session('kata_sandi_baru') }}</div>
  </div>
@endif

<div class="info">
  Penugasan peran tercatat penuh di Log Audit dan dapat ditinjau independen oleh Auditor.
  Anda tidak dapat mengubah peran akun Anda sendiri — pagar anti eskalasi hak mandiri.
</div>

<div class="kepala">
  <h2>Manajemen pengguna</h2>
  <p>Seluruh akun login · kelola akun teknis dan penugasan peran saja — bukan data cuti/lembur/pegawai</p>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Nama</th><th>NRP</th><th>Email</th><th>Peran</th><th>2FA</th><th>Tindakan</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($users as $u)
        @php $diriSendiri = $u->id === auth()->id(); @endphp
        <tr>
          <td class="peg">{{ $u->name }}</td>
          <td class="angka">{{ $u->employee?->nrp ?? '—' }}</td>
          <td>{{ $u->email }}</td>
          <td>
            @forelse ($u->roles as $r)
              <span class="peran-tag">
                {{ \App\Modules\Access\Domain\Role::tryFrom($r->name)?->label() ?? $r->name }}
                @unless ($diriSendiri)
                  <form method="POST" action="{{ route('sysadmin.users.revoke-role', [$u->id, $r->name]) }}"
                    data-confirm="Cabut peran {{ $r->name }} dari {{ $u->name }}?">
                    @csrf
                    <button type="submit" title="Cabut peran">×</button>
                  </form>
                @endunless
              </span>
            @empty
              <span style="color:var(--teks-lemah);font-size:11.5px">Tidak ada peran</span>
            @endforelse
          </td>
          <td>
            @if ($u->two_factor_confirmed_at)
              <span class="peran-tag" style="background:var(--hijau-muda);color:var(--hijau-tua)">Aktif</span>
            @else
              <span style="color:var(--teks-lemah);font-size:11.5px">Nonaktif</span>
            @endif
          </td>
          <td>
            <div class="tindakan-kolom">
              <form method="POST" action="{{ route('sysadmin.users.reset-password', $u->id) }}"
                data-confirm="Reset kata sandi {{ $u->name }}? Kata sandi lama akan langsung tidak berlaku.">
                @csrf
                <button class="mini" type="submit">Reset Kata Sandi</button>
              </form>

              @if ($u->two_factor_confirmed_at)
                <form method="POST" action="{{ route('sysadmin.users.reset-two-factor', $u->id) }}"
                  data-confirm="Reset 2FA {{ $u->name }}? Perangkat authenticator lama tidak lagi berlaku, akan diminta setup ulang.">
                  @csrf
                  <button class="mini" type="submit">Reset 2FA</button>
                </form>
              @endif

              @if ($diriSendiri)
                <span class="diri-sendiri">Akun Anda sendiri — peran tidak dapat diubah di sini</span>
              @else
                <form method="POST" action="{{ route('sysadmin.users.assign-role', $u->id) }}" class="beri-peran">
                  @csrf
                  <select name="role" required>
                    <option value="" disabled selected>+ Beri peran…</option>
                    @foreach ($availableRoles as $role)
                      <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                  </select>
                  <button class="mini" type="submit">Beri</button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
