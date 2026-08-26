@extends('layouts.app')

@section('judul', 'Bank Soal')
@section('peran', 'Admin HC / Admin Sistem')

@section('gaya')
.kepala{margin-bottom:16px}
.kepala h2{font-size:17px;font-weight:700;letter-spacing:-.02em}
.kepala p{font-size:12.5px;color:var(--teks-lemah);margin-top:3px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:16px}
.bidang{margin-bottom:10px}
.bidang label{display:block;font-size:11px;font-weight:600;color:var(--teks-lemah);margin-bottom:5px}
.bidang input,.bidang select,.bidang textarea{width:100%;padding:8px 10px;border:1px solid var(--garis);
  border-radius:7px;font-family:inherit;font-size:12.5px}
.baris{display:flex;gap:8px;flex-wrap:wrap}
.baris .bidang{flex:1;min-width:160px}
.opsi{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin-bottom:10px}
.gulir{overflow-x:auto;background:var(--putih);border:1px solid var(--garis);border-radius:var(--r)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--teks-lemah);padding:11px 12px;
  border-bottom:1px solid var(--garis);white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--garis);font-size:12.5px}
tbody tr:last-child td{border-bottom:0}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
@endsection

@section('isi')
<div class="kepala">
  <h2>Bank soal — {{ $assessment->title }}</h2>
  <p>Soal pilihan ganda dinilai otomatis; soal esai menunggu penilaian manual</p>
</div>

<div class="kartu">
  @if (session('gagal'))
    <div class="pesan gagal">{{ session('gagal') }}</div>
  @endif
  <form method="POST" action="{{ route('lms.admin.assessments.questions.store', $assessment->id) }}">
    @csrf
    <div class="baris">
      <div class="bidang" style="max-width:100px">
        <label>Urutan</label>
        <input type="number" name="sequence" required min="1">
      </div>
      <div class="bidang" style="max-width:180px">
        <label>Tipe</label>
        <select name="type" onchange="document.getElementById('opsi-pg').style.display = this.value === 'multiple_choice' ? 'block' : 'none'">
          <option value="multiple_choice">Pilihan Ganda</option>
          <option value="essay">Esai</option>
        </select>
      </div>
      <div class="bidang" style="max-width:120px">
        <label>Bobot Nilai</label>
        <input type="number" name="score_weight" required value="1" min="0.01" step="0.01">
      </div>
    </div>
    <div class="bidang">
      <label>Pertanyaan</label>
      <textarea name="question_text" required rows="2"></textarea>
    </div>
    <div id="opsi-pg">
      <div class="opsi">
        @foreach (['A', 'B', 'C', 'D'] as $label)
          <div class="bidang">
            <label>Opsi {{ $label }}</label>
            <input type="text" name="options[{{ $label }}]" maxlength="500">
          </div>
        @endforeach
      </div>
      <div class="bidang" style="max-width:160px">
        <label>Jawaban Benar</label>
        <select name="correct_option">
          <option value="">—</option>
          @foreach (['A', 'B', 'C', 'D'] as $label)
            <option value="{{ $label }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <button type="submit" class="mini utama">Tambah Soal</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>No</th><th>Pertanyaan</th><th>Tipe</th><th>Bobot</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($questions as $q)
        <tr>
          <td class="angka">{{ $q->sequence }}</td>
          <td>{{ $q->question_text }}
            @if ($q->type === 'multiple_choice')
              <br><small style="color:var(--teks-lemah)">Jawaban benar: {{ $q->correct_option }}</small>
            @endif
          </td>
          <td><span class="tag">{{ $q->type === 'multiple_choice' ? 'Pilihan Ganda' : 'Esai' }}</span></td>
          <td class="angka">{{ $q->score_weight }}</td>
          <td>
            <form method="POST" action="{{ route('lms.admin.assessments.questions.destroy', [$assessment->id, $q->id]) }}"
                  data-confirm="Hapus soal ini?">
              @csrf @method('DELETE')
              <button type="submit" class="mini">Hapus</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Belum ada soal.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
