@extends('layouts.app')

@section('judul', 'Wawancara Keluar')
@section('peran', 'Employee Self Service')

@section('gaya')
.info{margin-bottom:16px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}
@endsection

@section('isi')
<div class="kartu" style="max-width:560px">
  <div class="kartu-judul">Wawancara Keluar</div>

  @if ($errors->any())
    <div class="pesan gagal">{{ $errors->first() }}</div>
  @endif

  <div class="info">
    Masukan Anda membantu Bank NTB Syariah meningkatkan pengalaman kerja pegawai. Formulir ini bersifat opsional dan hanya dapat diisi satu kali.
  </div>

  <form method="POST" action="{{ route('offboarding.exit-interview-store') }}">
    @csrf
    <div class="bidang">
      <label for="reason_detail">Alasan Anda meninggalkan Bank NTB Syariah</label>
      <textarea id="reason_detail" name="reason_detail" rows="3" maxlength="1000"></textarea>
    </div>

    <div class="baris-bidang">
      <div class="bidang">
        <label for="satisfaction_rating">Tingkat Kepuasan Bekerja (1-5)</label>
        <select id="satisfaction_rating" name="satisfaction_rating">
          <option value="">— Pilih —</option>
          @for ($i = 1; $i <= 5; $i++)
            <option value="{{ $i }}">{{ $i }}</option>
          @endfor
        </select>
      </div>
      <div class="bidang">
        <label for="would_recommend">Akan merekomendasikan Bank NTB Syariah sebagai tempat kerja?</label>
        <select id="would_recommend" name="would_recommend">
          <option value="">— Pilih —</option>
          <option value="1">Ya</option>
          <option value="0">Tidak</option>
        </select>
      </div>
    </div>

    <div class="bidang">
      <label for="comments">Komentar/Saran Tambahan</label>
      <textarea id="comments" name="comments" rows="3" maxlength="1000"></textarea>
    </div>

    <button type="submit" class="btn">Kirim</button>
  </form>
</div>
@endsection
