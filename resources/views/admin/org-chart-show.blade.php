@extends('layouts.app')

@section('judul', 'Struktur Organisasi')
@section('peran', 'Admin Sistem / Admin HC')

@section('gaya')
.kepala{margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.aksi{display:flex;gap:8px}
.btn-kecil{padding:8px 14px;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;
  cursor:pointer;border:1px solid var(--garis);background:var(--putih);text-decoration:none;color:var(--teks)}
.btn-kecil:hover{background:var(--latar)}
.btn-kecil.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.btn-kecil.utama:hover{background:var(--hijau-tua)}

.panggung{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:32px 20px}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}

/* Bagan org murni CSS — pola konektor ::before/::after standar,
   tanpa JS/library tambahan. */
.bagan, .bagan ul{list-style:none;text-align:center;white-space:nowrap}
.bagan{display:inline-block;padding-top:8px}
.bagan ul{display:flex;padding-top:24px;position:relative}
.bagan li{display:table-cell;vertical-align:top;padding:0 10px;position:relative}
.bagan li::before, .bagan li::after{content:'';position:absolute;top:0;right:50%;
  border-top:2px solid var(--garis);width:50%;height:24px}
.bagan li::after{right:auto;left:50%;border-left:2px solid var(--garis)}
.bagan li:only-child::before, .bagan li:only-child::after{display:none}
.bagan li:only-child{padding-top:0}
.bagan li:first-child::before{border:0 none}
.bagan li:last-child::after{border:0 none}
.bagan li:last-child::before{border-right:2px solid var(--garis);border-radius:0 8px 0 0}
.bagan li:first-child::after{border-radius:8px 0 0 0}
.bagan ul::before{content:'';position:absolute;top:0;left:50%;border-left:2px solid var(--garis);width:0;height:24px}
.bagan li:only-child::before, .bagan li:only-child::after{display:none}

.kotak{display:inline-flex;flex-direction:column;align-items:center;gap:6px;padding:12px 14px 10px;
  border:1px solid var(--garis);border-radius:12px;background:var(--putih);min-width:150px;
  box-shadow:0 1px 2px rgba(15,31,26,.04);white-space:normal}
.avatar{width:52px;height:52px;border-radius:99px;background:linear-gradient(135deg,var(--hijau),var(--hijau-tua));
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;
  border:3px solid var(--hijau-muda)}
.kotak .nm{font-weight:700;font-size:12.5px;line-height:1.3}
.kotak .jb{font-size:10.5px;color:var(--teks-lemah)}
.kotak .np{font-size:9.5px;color:var(--teks-lemah);font-family:'JetBrains Mono',monospace}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>Struktur organisasi — {{ $judulUnit }}</h2>
    <p>Dibangun dari Atasan Langsung yang ditetapkan tiap pegawai — murni tampilan, tidak memengaruhi wewenang persetujuan</p>
  </div>
  <div class="aksi">
    <a href="{{ route('org-chart.index') }}" class="btn-kecil">← Pilih unit lain</a>
    <a href="{{ route('org-chart.pdf', array_filter(['officeId' => $office->id, 'divisi' => $divisi])) }}" class="btn-kecil utama">Unduh PDF</a>
  </div>
</div>

<div class="panggung">
  @if ($tree->isEmpty())
    <div class="kosong">Belum ada pegawai pada unit ini.</div>
  @else
    <ul class="bagan">
      @foreach ($tree as $node)
        @include('admin._org-chart-person-node', ['node' => $node])
      @endforeach
    </ul>
  @endif
</div>
@endsection
