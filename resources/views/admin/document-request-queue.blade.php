@extends('layouts.app')

@section('judul', 'Layanan Dokumen Mandiri')
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.sub-judul{font-size:13px;font-weight:700;margin:22px 0 10px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
.peg{font-weight:600}
.peg small{display:block;font-weight:400;color:var(--teks-lemah);font-size:11px;margin-top:2px}
.aksi{display:flex;gap:6px;flex-wrap:wrap}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih);
  text-decoration:none;color:var(--teks);transition:.12s}
.mini:hover{background:var(--latar)}
.mini.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.mini.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Layanan Dokumen Mandiri</h2>
  <p>Permintaan dokumen dari pegawai dalam lingkup kewenangan Anda</p>
</div>

<div class="sub-judul">Menunggu Diproses</div>
<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Jenis Dokumen</th><th>Keperluan</th><th>Diajukan</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($requests as $r)
        <tr>
          <td class="peg">{{ $r->full_name }}<small>{{ $r->nrp }}</small></td>
          <td>{{ $documentTypes[$r->document_type] ?? $r->document_type }}</td>
          <td>{{ $r->purpose }}</td>
          <td class="angka">{{ date('j M Y', strtotime($r->created_at)) }}</td>
          <td>
            <div class="aksi">
              <form method="POST" action="{{ route('admin.document-request-issue', $r->id) }}">
                @csrf
                <button class="mini utama" type="submit">Terbitkan</button>
              </form>
              <form method="POST" action="{{ route('admin.document-request-reject', $r->id) }}" onsubmit="mintaAlasanTolak(this, event); return false;">
                @csrf
                <button class="mini" type="submit">Tolak</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="kosong">Tidak ada permintaan yang menunggu diproses.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="sub-judul">Sudah Diterbitkan — Menunggu Tanda Tangan</div>
<div class="gulir">
  <table>
    <thead>
      <tr><th>Pegawai</th><th>Jenis Dokumen</th><th>Diterbitkan</th><th>Tindakan</th></tr>
    </thead>
    <tbody>
      @forelse ($awaitingSignature as $r)
        <tr>
          <td class="peg">{{ $r->full_name }}<small>{{ $r->nrp }}</small></td>
          <td>{{ $documentTypes[$r->document_type] ?? $r->document_type }}</td>
          <td class="angka">{{ $r->processed_at ? date('j M Y', strtotime($r->processed_at)) : '—' }}</td>
          <td>
            <div class="aksi">
              <a href="{{ route('admin.document-request-download', $r->id) }}" class="mini">Unduh</a>
              <details>
                <summary class="mini" style="display:inline-block">Tandatangani</summary>
                @include('admin._signature-pad', [
                  'signAction' => route('signature.store', ['signableType' => 'document_request', 'signableId' => $r->id]),
                  'contextLabel' => 'dokumen '.($documentTypes[$r->document_type] ?? $r->document_type).' a.n. '.$r->full_name,
                ])
              </details>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="kosong">Tidak ada dokumen yang menunggu tanda tangan.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
