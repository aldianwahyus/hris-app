@extends('layouts.app')

@section('judul', 'Log Audit')
@section('peran', 'Auditor')

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
tbody tr:hover{background:#FAFCFB}
.laku{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap;
  background:var(--hijau-muda);color:var(--hijau-tua)}
.laku.rejected,.laku.expired{background:var(--merah-muda);color:var(--merah)}
.laku.reminded,.laku.submitted,.laku.password_reset{background:var(--emas-muda);color:#7A5F0B}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.sistem{color:var(--teks-lemah);font-style:italic}
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px;flex-shrink:0}
.ekspor:hover{background:var(--hijau-tua)}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Log audit</h2>
    <p>100 entri terbaru, seluruh bank (BANK_WIDE) · append-only — tidak ada aksi ubah/hapus di sini</p>
  </div>
  <a href="{{ route('audit.index.export') }}" class="ekspor">⬇ Ekspor CSV</a>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Objek</th><th>Dasar</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($entries as $e)
        <tr>
          <td class="angka">{{ date('j M Y H:i', strtotime($e->occurred_at)) }}</td>
          <td>
            @if ($e->actor_name)
              {{ $e->actor_name }} <span style="color:var(--teks-lemah)">({{ $e->actor_nrp }})</span>
            @else
              <span class="sistem">{{ $e->actor_role ?? 'sistem' }}</span>
            @endif
          </td>
          <td><span class="laku {{ $e->action }}">{{ $e->action }}</span></td>
          <td>{{ $e->auditable_type }} <span class="angka" style="color:var(--teks-lemah)">{{ \Illuminate\Support\Str::limit($e->auditable_id, 8, '…') }}</span></td>
          <td>{{ $e->context_ref ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Belum ada entri audit.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
