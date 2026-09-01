@extends('layouts.app')

@section('judul', 'Log Audit')
@section('peran', 'Auditor / Pejabat SDM / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kepala{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.filter{display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.filter input,.filter select{padding:7px 10px;border:1px solid var(--garis);border-radius:7px;font-family:inherit;font-size:12.5px}
.filter button{padding:7px 14px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);
  font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
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
.ekspor{padding:7px 14px;border-radius:7px;border:1px solid var(--hijau);background:var(--hijau);
  color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px;flex-shrink:0}
.ekspor:hover{background:var(--hijau-tua)}
details.rincian summary{cursor:pointer;color:var(--hijau-tua);font-size:11px;font-weight:600}
details.rincian pre{margin-top:6px;background:var(--latar);border-radius:6px;padding:8px;
  font-size:10.5px;white-space:pre-wrap;word-break:break-word;max-width:420px}
.halaman{display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--teks-lemah)}
.halaman a{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);background:var(--putih);color:var(--teks);font-weight:600}
.halaman .nonaktif{padding:6px 12px;border-radius:7px;border:1px solid var(--garis);color:var(--teks-lemah);opacity:.5}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Log audit</h2>
    <p>Seluruh bank (BANK_WIDE) · append-only — tidak ada aksi ubah/hapus di sini</p>
  </div>
  <a href="{{ route('audit.index.export', request()->query()) }}" class="ekspor">⬇ Ekspor CSV (sesuai filter)</a>
</div>

<form method="GET" class="filter">
  <select name="modul">
    <option value="">Semua modul</option>
    @foreach ($modules as $m)
      <option value="{{ $m }}" @selected($filters['modul'] === $m)>{{ $m }}</option>
    @endforeach
  </select>
  <input type="text" name="aktor" placeholder="Nama/NRP aktor" value="{{ $filters['aktor'] }}">
  <span style="font-size:12.5px">Dari</span>
  <input type="date" name="dari" value="{{ $filters['dari']?->format('Y-m-d') }}">
  <span style="font-size:12.5px">s.d.</span>
  <input type="date" name="sampai" value="{{ $filters['sampai']?->format('Y-m-d') }}">
  <button type="submit">Saring</button>
  @if ($filters['modul'] || $filters['aktor'] || $filters['dari'] || $filters['sampai'])
    <a href="{{ route('audit.index') }}" style="font-size:12px">Hapus filter</a>
  @endif
</form>

<div class="gulir">
  <table>
    <thead>
      <tr>
        <th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Objek</th><th>Dasar</th><th>Rincian</th>
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
          <td>
            @if ($e->old_values || $e->new_values)
              <details class="rincian">
                <summary>Lihat</summary>
                @if ($e->old_values)
                  <div style="font-size:10px;font-weight:700;margin-top:4px">Sebelum</div>
                  <pre>{{ json_encode(json_decode($e->old_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
                @if ($e->new_values)
                  <div style="font-size:10px;font-weight:700">Sesudah</div>
                  <pre>{{ json_encode(json_decode($e->new_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
              </details>
            @else
              <span style="color:var(--teks-lemah)">—</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="kosong">Tidak ada entri audit yang cocok dengan filter ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="halaman">
  <span>Halaman {{ $entries->currentPage() }} dari {{ max($entries->lastPage(), 1) }} — {{ $entries->total() }} entri cocok</span>
  <span>
    @if ($entries->onFirstPage())
      <span class="nonaktif">← Sebelumnya</span>
    @else
      <a href="{{ $entries->previousPageUrl() }}">← Sebelumnya</a>
    @endif
    @if ($entries->hasMorePages())
      <a href="{{ $entries->nextPageUrl() }}">Berikutnya →</a>
    @else
      <span class="nonaktif">Berikutnya →</span>
    @endif
  </span>
</div>
@endsection
