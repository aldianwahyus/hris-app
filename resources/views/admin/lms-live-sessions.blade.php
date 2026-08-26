@extends('layouts.app')

@section('judul', 'Sesi Live & Mentoring')
@section('peran', 'Admin HC / Admin Sistem')

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
tbody td{padding:9px 10px;border-bottom:1px solid var(--garis);font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody input{padding:5px 7px;border:1px solid var(--garis);border-radius:6px;font-family:inherit;font-size:11.5px;width:100%}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.mini{padding:6px 12px;border-radius:7px;font-family:inherit;font-size:11.5px;
  font-weight:600;cursor:pointer;border:1px solid var(--garis);background:var(--putih)}
.mini:hover{background:var(--latar)}
.utama{background:var(--hijau);color:#fff;border-color:var(--hijau)}
.utama:hover{background:var(--hijau-tua)}
.kosong{padding:36px;text-align:center;color:var(--teks-lemah);font-size:13px}
.centang{display:flex;align-items:center;gap:4px;justify-content:center}
@endsection

@section('isi')
<div class="kepala">
  <h2>Sesi live &amp; mentoring</h2>
  <p>Webinar/coaching/mentoring (BRD §5.10) — tautan rapat/rekaman eksternal, tidak ada hosting video sendiri</p>
</div>

<div class="kartu">
  <form method="POST" action="{{ route('lms.admin.live-sessions.store') }}" class="baris-tambah">
    @csrf
    <div class="bidang-kecil" style="flex:1;min-width:180px">
      <label>Judul</label>
      <input type="text" name="title" required maxlength="200">
    </div>
    <div class="bidang-kecil">
      <label>Tipe</label>
      <select name="session_type" required>
        <option value="webinar">Webinar</option>
        <option value="coaching">Coaching</option>
        <option value="mentoring">Mentoring</option>
      </select>
    </div>
    <div class="bidang-kecil" style="min-width:160px">
      <label>Fasilitator</label>
      <select name="facilitator_employee_id">
        <option value="">— Tidak ditentukan —</option>
        @foreach ($employees as $e)
          <option value="{{ $e->id }}">{{ $e->full_name }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil" style="min-width:160px">
      <label>Kursus Terkait</label>
      <select name="course_id">
        <option value="">— Tidak terikat —</option>
        @foreach ($courses as $c)
          <option value="{{ $c->id }}">{{ $c->title }}</option>
        @endforeach
      </select>
    </div>
    <div class="bidang-kecil">
      <label>Jadwal</label>
      <input type="datetime-local" name="scheduled_at" required>
    </div>
    <div class="bidang-kecil" style="min-width:200px">
      <label>Tautan Rapat</label>
      <input type="url" name="meeting_url" placeholder="https://...">
    </div>
    <button type="submit" class="mini utama">Tambah Sesi</button>
  </form>
</div>

<div class="gulir">
  <table>
    <thead>
      <tr><th>Judul</th><th>Tipe</th><th>Fasilitator</th><th>Jadwal</th><th>Rekaman</th><th>Peserta</th><th>Aktif</th><th></th></tr>
    </thead>
    <tbody>
      @forelse ($sessions as $s)
        @php $formId = 'sesi-'.$s->id; @endphp
        <tr>
          <td>{{ $s->title }}</td>
          <td><span class="tag">{{ $s->session_type }}</span></td>
          <td>{{ $s->facilitator_name ?? '—' }}</td>
          <td class="angka">{{ date('j M Y H:i', strtotime($s->scheduled_at)) }}</td>
          <td><input form="{{ $formId }}" type="url" name="recording_url" value="{{ $s->recording_url }}" placeholder="Tautan rekaman..."></td>
          <td class="angka">{{ $participantCounts[$s->id] ?? 0 }}</td>
          <td class="centang">
            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($s->is_active)>
          </td>
          <td>
            <button form="{{ $formId }}" type="submit" class="mini">Simpan</button>
            <a href="{{ route('lms.admin.live-sessions.participants', $s->id) }}" class="mini">Peserta</a>
            <form id="{{ $formId }}" method="POST" action="{{ route('lms.admin.live-sessions.update', $s->id) }}" style="display:none">
              @csrf
              <input type="hidden" name="meeting_url" value="{{ $s->meeting_url }}">
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="kosong">Belum ada sesi.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
