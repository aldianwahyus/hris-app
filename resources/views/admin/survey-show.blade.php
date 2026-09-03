@extends('layouts.app')

@section('judul', $survey->title)
@section('peran', 'Admin SDM')

@section('gaya')
.kepala{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.draft{background:#EDEDED;color:#6B6B6B}
.status.aktif{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.selesai{background:#DCEAFB;color:#1D4E89}
.ringkas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:11px;margin-bottom:20px}
.ring{background:var(--putih);border:1px solid var(--garis);border-radius:10px;padding:14px}
.ring .a{font-size:25px;font-weight:800;letter-spacing:-.03em}
.ring .l{font-size:11.5px;color:var(--teks-lemah);margin-top:3px;font-weight:500}
.pertanyaan-hasil{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:14px}
.pertanyaan-hasil h3{font-size:13px;font-weight:700;margin-bottom:10px}
.bar-baris{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:11.5px}
.bar-label{width:110px;flex-shrink:0;color:var(--teks-lemah)}
.bar-luar{flex:1;background:var(--latar);border-radius:6px;overflow:hidden;height:16px}
.bar-dalam{background:var(--hijau);height:100%;border-radius:6px}
.bar-angka{width:44px;text-align:right;flex-shrink:0;font-weight:600}
.jawaban-teks{padding:8px 10px;background:var(--latar);border-radius:7px;font-size:12px;margin-bottom:6px;line-height:1.5}
.aksi{display:flex;gap:8px}
@endsection

@section('isi')
<div class="kepala">
  <div>
    <h2>{{ $survey->title }}</h2>
    <p>{{ date('j M Y', strtotime($survey->start_date)) }} – {{ date('j M Y', strtotime($survey->end_date)) }}
      &middot; {{ $survey->scope === 'bank_wide' ? 'Seluruh Bank' : 'Satu Kantor' }}
      {{ $survey->is_anonymous ? '· Anonim' : '' }}</p>
  </div>
  <div class="aksi">
    <span class="status {{ $survey->status }}">{{ ['draft' => 'Draf', 'aktif' => 'Aktif', 'selesai' => 'Selesai'][$survey->status] ?? $survey->status }}</span>
    @if ($survey->status === 'draft')
      <form method="POST" action="{{ route('admin.survey-publish', $survey->id) }}">
        @csrf
        <button type="submit" class="btn" style="padding:7px 14px;font-size:12px">Terbitkan</button>
      </form>
    @elseif ($survey->status === 'aktif')
      <form method="POST" action="{{ route('admin.survey-close', $survey->id) }}" data-confirm="Tutup survei ini? Pegawai tidak akan bisa mengisi lagi.">
        @csrf
        <button type="submit" class="btn luar" style="padding:7px 14px;font-size:12px">Tutup Survei</button>
      </form>
    @endif
  </div>
</div>

<div class="ringkas">
  <div class="ring">
    <div class="a angka">{{ $results['response_count'] }}</div>
    <div class="l">Total responden</div>
  </div>
  <div class="ring">
    <div class="a angka">{{ $questions->count() }}</div>
    <div class="l">Pertanyaan</div>
  </div>
</div>

@foreach ($results['questions'] as $q)
  <div class="pertanyaan-hasil">
    <h3>{{ $q['question_text'] }}</h3>

    @if ($q['question_type'] === 'nps_0_10')
      @php $s = $q['summary']; @endphp
      <div class="ring" style="display:inline-block;margin-bottom:10px">
        <div class="a angka">{{ $s['score'] }}</div>
        <div class="l">Skor eNPS</div>
      </div>
      <div class="bar-baris">
        <span class="bar-label">Promoter (9-10)</span>
        <div class="bar-luar"><div class="bar-dalam" style="width:{{ $s['total'] > 0 ? round($s['promoter'] / $s['total'] * 100) : 0 }}%"></div></div>
        <span class="bar-angka">{{ $s['promoter'] }}</span>
      </div>
      <div class="bar-baris">
        <span class="bar-label">Pasif (7-8)</span>
        <div class="bar-luar"><div class="bar-dalam" style="width:{{ $s['total'] > 0 ? round($s['passive'] / $s['total'] * 100) : 0 }}%"></div></div>
        <span class="bar-angka">{{ $s['passive'] }}</span>
      </div>
      <div class="bar-baris">
        <span class="bar-label">Detractor (0-6)</span>
        <div class="bar-luar"><div class="bar-dalam" style="width:{{ $s['total'] > 0 ? round($s['detractor'] / $s['total'] * 100) : 0 }}%"></div></div>
        <span class="bar-angka">{{ $s['detractor'] }}</span>
      </div>
    @elseif ($q['question_type'] === 'rating_1_5')
      @php $s = $q['summary']; @endphp
      <div class="ring" style="display:inline-block;margin-bottom:10px">
        <div class="a angka">{{ $s['average'] }}</div>
        <div class="l">Rata-rata (skala 1-5)</div>
      </div>
      @foreach ($s['distribution'] as $bintang => $jumlah)
        <div class="bar-baris">
          <span class="bar-label">{{ $bintang }} bintang</span>
          <div class="bar-luar"><div class="bar-dalam" style="width:{{ $s['total'] > 0 ? round($jumlah / $s['total'] * 100) : 0 }}%"></div></div>
          <span class="bar-angka">{{ $jumlah }}</span>
        </div>
      @endforeach
    @elseif ($q['question_type'] === 'pilihan_ganda')
      @php $s = $q['summary']; @endphp
      @foreach ($s['counts'] as $opsi => $jumlah)
        <div class="bar-baris">
          <span class="bar-label">{{ $opsi }}</span>
          <div class="bar-luar"><div class="bar-dalam" style="width:{{ $s['total'] > 0 ? round($jumlah / $s['total'] * 100) : 0 }}%"></div></div>
          <span class="bar-angka">{{ $jumlah }}</span>
        </div>
      @endforeach
    @else
      @forelse ($q['summary']['jawaban'] as $jawaban)
        <div class="jawaban-teks">{{ $jawaban }}</div>
      @empty
        <div class="jawaban-teks">Belum ada jawaban.</div>
      @endforelse
    @endif
  </div>
@endforeach
@endsection
