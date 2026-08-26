<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>CV — {{ $employee->full_name }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:12px;color:#1a1a1a;margin:32px}
  .kop{text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:18px}
  .kop h1{font-size:15px;margin:0 0 2px}
  .kop p{margin:0;font-size:11px;color:#444}
  .judul{text-align:center;margin-bottom:16px}
  .judul h2{font-size:13px;text-decoration:underline;margin:0}
  .bagian{margin-top:18px}
  .bagian h3{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;
    border-bottom:1px solid #999;padding-bottom:4px;margin:0 0 8px}
  table.data{width:100%;border-collapse:collapse;margin-bottom:6px}
  table.data td{padding:4px 6px;vertical-align:top;font-size:11.5px}
  table.data td.label{width:170px;color:#333}
  table.data td.sep{width:14px}
  table.riwayat{width:100%;border-collapse:collapse;margin:6px 0 4px}
  table.riwayat th,table.riwayat td{border:1px solid #999;padding:5px 7px;font-size:10.5px}
  table.riwayat th{background:#f0f0f0;text-align:left}
  .kosong{font-size:10.5px;color:#777;font-style:italic;margin:4px 0}
  .catatan{margin-top:24px;font-size:10px;color:#666}
</style>
</head>
<body>
  <div class="kop">
    <h1>Bank NTB Syariah</h1>
    <p>Kantor Pusat — Jl. Pejanggik No. 30 Mataram</p>
  </div>

  <div class="judul">
    <h2>CURRICULUM VITAE PEGAWAI</h2>
  </div>

  <div class="bagian">
    <h3>Data Organisasi</h3>
    <table class="data">
      <tr><td class="label">NRP</td><td class="sep">:</td><td>{{ $employee->nrp }}</td></tr>
      <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $employee->full_name }}</td></tr>
      <tr><td class="label">Kantor</td><td class="sep">:</td><td>{{ $employee->office_name }}</td></tr>
      <tr><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $employee->position_name }}</td></tr>
      <tr><td class="label">Status Kepegawaian</td><td class="sep">:</td><td>{{ ucfirst($employee->employment_status) }}</td></tr>
      <tr><td class="label">Tanggal Bergabung</td><td class="sep">:</td><td>{{ $employee->join_date }}</td></tr>
    </table>
  </div>

  <div class="bagian">
    <h3>Data Pribadi</h3>
    <table class="data">
      <tr><td class="label">Alamat</td><td class="sep">:</td><td>{{ $employee->alamat ?? '-' }}</td></tr>
      <tr><td class="label">No. Telepon</td><td class="sep">:</td><td>{{ $employee->no_telepon ?? '-' }}</td></tr>
      <tr><td class="label">Kontak Darurat</td><td class="sep">:</td>
        <td>{{ $employee->kontak_darurat_nama ?? '-' }}
          @if ($employee->kontak_darurat_hubungan) ({{ $employee->kontak_darurat_hubungan }}) @endif
          @if ($employee->kontak_darurat_telepon) — {{ $employee->kontak_darurat_telepon }} @endif
        </td></tr>
      <tr><td class="label">Pendidikan Terakhir</td><td class="sep">:</td>
        <td>{{ $employee->pendidikan_terakhir ?? '-' }} {{ $employee->pendidikan_jurusan ? '— '.$employee->pendidikan_jurusan : '' }}</td></tr>
    </table>
  </div>

  <div class="bagian">
    <h3>Riwayat SK</h3>
    @if ($decisionLetters->isEmpty())
      <p class="kosong">Belum ada data.</p>
    @else
      @php $labelJenis = ['mutasi' => 'Mutasi', 'promosi' => 'Promosi', 'sanksi' => 'Sanksi', 'lainnya' => 'Lainnya']; @endphp
      <table class="riwayat">
        <thead><tr><th>Jenis</th><th>Nomor SK</th><th>Tanggal</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach ($decisionLetters as $sk)
            <tr>
              <td>{{ $labelJenis[$sk->sk_type] ?? $sk->sk_type }}</td>
              <td>{{ $sk->sk_number }}</td>
              <td>{{ $sk->sk_date }}</td>
              <td>{{ $sk->description }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="bagian">
    <h3>Riwayat Pelatihan</h3>
    @if ($trainings->isEmpty())
      <p class="kosong">Belum ada data.</p>
    @else
      <table class="riwayat">
        <thead><tr><th>Nama Pelatihan</th><th>Penyelenggara</th><th>Periode</th></tr></thead>
        <tbody>
          @foreach ($trainings as $t)
            <tr>
              <td>{{ $t->training_name }}</td>
              <td>{{ $t->organizer ?? '-' }}</td>
              <td>{{ $t->start_date ?? '-' }}{{ $t->end_date ? ' s.d. '.$t->end_date : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="bagian">
    <h3>Riwayat Sertifikasi</h3>
    @if ($certifications->isEmpty())
      <p class="kosong">Belum ada data.</p>
    @else
      <table class="riwayat">
        <thead><tr><th>Nama Sertifikasi</th><th>Penerbit</th><th>No. Sertifikat</th><th>Terbit</th><th>Berlaku s.d.</th></tr></thead>
        <tbody>
          @foreach ($certifications as $c)
            <tr>
              <td>{{ $c->certification_name }}</td>
              <td>{{ $c->issuer ?? '-' }}</td>
              <td>{{ $c->certificate_number ?? '-' }}</td>
              <td>{{ $c->issued_date ?? '-' }}</td>
              <td>{{ $c->expiry_date ?? '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="bagian">
    <h3>Riwayat Organisasi</h3>
    @if ($organizations->isEmpty())
      <p class="kosong">Belum ada data.</p>
    @else
      <table class="riwayat">
        <thead><tr><th>Nama Organisasi</th><th>Peran</th><th>Periode</th></tr></thead>
        <tbody>
          @foreach ($organizations as $o)
            <tr>
              <td>{{ $o->organization_name }}</td>
              <td>{{ $o->role ?? '-' }}</td>
              <td>{{ $o->start_date ?? '-' }}{{ $o->end_date ? ' s.d. '.$o->end_date : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="bagian">
    <h3>Riwayat Penghargaan</h3>
    @if ($awards->isEmpty())
      <p class="kosong">Belum ada data.</p>
    @else
      <table class="riwayat">
        <thead><tr><th>Nama Penghargaan</th><th>Pemberi</th><th>Tanggal</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach ($awards as $a)
            <tr>
              <td>{{ $a->award_name }}</td>
              <td>{{ $a->issuer ?? '-' }}</td>
              <td>{{ $a->award_date ?? '-' }}</td>
              <td>{{ $a->description ?? '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <p class="catatan">
    Dokumen ini dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA.
  </p>
</body>
</html>
