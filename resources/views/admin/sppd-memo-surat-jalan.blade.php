<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat Jalan {{ $group->group_number }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:10.5px;color:#1a1a1a;margin:26px}
  .kop{text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:8px;margin-bottom:10px}
  .kop p{margin:0;font-size:10px;line-height:1.4}
  .judul{text-align:center;font-weight:700;font-size:12px;text-decoration:underline;margin-bottom:2px}
  .nomor{text-align:center;font-size:10.5px;margin-bottom:10px}
  table.utama{width:100%;border-collapse:collapse}
  table.utama > tbody > tr > td{border:1px solid #1a1a1a;padding:5px 7px;vertical-align:top}
  .no{width:22px;text-align:center}
  .lbl{width:230px}
  table.pengikut{width:100%;border-collapse:collapse;margin-top:4px}
  table.pengikut th,table.pengikut td{border:1px solid #1a1a1a;padding:4px 6px;font-size:10px}
  table.pengikut th{background:#f0f0f0;text-align:left}
  .ttd{margin-top:26px;text-align:right;font-size:10.5px}
  .ttd .garis{display:block;margin-top:56px;font-weight:700;text-decoration:underline}
  .kaki{margin-top:16px;font-size:8px;color:#777;border-top:1px solid #ccc;padding-top:4px}
  .halaman-2{page-break-before:always}
  table.itinerary{width:100%;border-collapse:collapse}
  table.itinerary td{border:1px solid #1a1a1a;padding:8px;font-size:10px;vertical-align:top;height:52px}
  .perhatian{margin-top:14px;font-size:9.5px;line-height:1.6;text-align:justify}
  .perhatian b{text-decoration:underline}
</style>
</head>
<body>
@php
  $utama = $travelers->first();
  $pengikut = $travelers->slice(1);
@endphp

<div class="kop">
  <img src="{{ \App\Interfaces\Http\Support\CompanyLogo::dataUri() }}" alt="Bank NTB Syariah" style="height:34px;float:right">
  <p>KANTOR PUSAT{{ $headOfficeAddress ? ' : '.$headOfficeAddress : '' }}</p>
</div>

<div class="judul">Surat Perintah Perjalanan Dinas</div>
<div class="nomor">Nomor : {{ $group->group_number }}</div>

<table class="utama">
  <tr>
    <td class="no">1</td>
    <td class="lbl">Pejabat yang memberi perintah</td>
    <td>{{ $group->authorizing_official_name ?? '—' }}{{ $group->authorizing_official_title ? ' ('.$group->authorizing_official_title.')' : '' }}</td>
  </tr>
  <tr>
    <td class="no">2</td>
    <td class="lbl">Nama pegawai yang diperintahkan mengadakan perjalanan</td>
    <td>{{ $utama->full_name ?? '—' }}</td>
  </tr>
  <tr>
    <td class="no">3</td>
    <td class="lbl">
      a. Person Grade<br>b. Jabatan<br>c. Unit Organisasi<br>d. Tingkat menurut PPD
    </td>
    <td>
      a. {{ $utama->person_grade ?? '—' }}<br>
      b. {{ $utama->position_name ?? '—' }}<br>
      c. {{ $utama->office_name ?? '—' }}<br>
      d. —
    </td>
  </tr>
  <tr>
    <td class="no">4</td>
    <td class="lbl">Maksud perjalanan dinas</td>
    <td>{{ $group->purpose }}</td>
  </tr>
  <tr>
    <td class="no">5</td>
    <td class="lbl">Alat angkut yang dipergunakan</td>
    <td>—</td>
  </tr>
  <tr>
    <td class="no">6</td>
    <td class="lbl">a. Tempat berangkat<br>b. Tempat tujuan</td>
    <td>a. {{ $utama->office_name ?? '—' }}<br>b. {{ $group->destination }}</td>
  </tr>
  <tr>
    <td class="no">7</td>
    <td class="lbl">a. Lamanya perjalanan dinas<br>b. Tanggal berangkat<br>c. Tanggal harus kembali</td>
    <td>
      a. {{ $group->total_days }} hari<br>
      b. {{ \Carbon\Carbon::parse($group->start_date)->format('d/m/Y') }}<br>
      c. {{ \Carbon\Carbon::parse($group->end_date)->format('d/m/Y') }}
    </td>
  </tr>
  <tr>
    <td class="no">8</td>
    <td class="lbl">Pengikut</td>
    <td>
      @if ($pengikut->isEmpty())
        —
      @else
        <table class="pengikut">
          <thead><tr><th style="width:24px">No</th><th>Nama</th><th style="width:60px">Grade</th><th>Jabatan</th></tr></thead>
          <tbody>
            @foreach ($pengikut as $i => $p)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->full_name }}</td>
                <td>{{ $p->person_grade ?? '—' }}</td>
                <td>{{ $p->position_name }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </td>
  </tr>
  <tr>
    <td class="no">9</td>
    <td class="lbl">Pembebanan Anggaran<br>a. Unit Kerja<br>b. Nomor Mata Anggaran</td>
    <td>a. —<br>b. —</td>
  </tr>
  <tr>
    <td class="no">10</td>
    <td class="lbl">Keterangan lain-lain</td>
    <td>{{ $group->source_division ? 'Memo: '.$group->memo_number.' — '.$group->source_division : 'Memo: '.$group->memo_number }}</td>
  </tr>
  <tr>
    <td class="no">11</td>
    <td colspan="2">
      <div class="ttd">
        Dikeluarkan di : Mataram<br>
        Pada tanggal : {{ \Carbon\Carbon::parse($group->memo_date)->translatedFormat('d F Y') }}<br>
        <span class="garis">{{ $group->authorizing_official_title ?? 'Pejabat Berwenang' }}</span>
        ( {{ $group->authorizing_official_name ?? '&nbsp;' }} )
      </div>
    </td>
  </tr>
</table>

<p class="kaki">
  Dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA
  berdasarkan SPPD Massal {{ $group->group_number }}.
</p>

<div class="halaman-2">
  <table class="itinerary">
    <tr>
      <td style="width:50%">I.</td>
      <td>Berangkat dari :<br><br>Tanggal :</td>
    </tr>
    <tr>
      <td>II. Tiba di :<br>Pada tanggal :<br>Kepala :</td>
      <td>Berangkat dari :<br>Ke :<br>Pada tanggal :<br>Kepala :</td>
    </tr>
    <tr>
      <td>III. Tiba di :<br>Pada tanggal :<br>Kepala :</td>
      <td>Berangkat dari :<br>Ke :<br>Pada tanggal :<br>Kepala :</td>
    </tr>
    <tr>
      <td>IV. Tiba di :<br>Pada tanggal :<br>Kepala :</td>
      <td>Berangkat dari :<br>Ke :<br>Pada tanggal :<br>Kepala :</td>
    </tr>
    <tr>
      <td>V. Tiba kembali di :<br>(tempat kedudukan)</td>
      <td>
        Telah diperiksa dengan keterangan bahwa perjalanan tersebut diatas benar dilakukan atas
        perintahnya dan semata-mata untuk kepentingan jabatan dalam waktu yang sesingkat-singkatnya.
        <br><br>
        PEJABAT YANG MEMBERI PERINTAH
      </td>
    </tr>
    <tr>
      <td>
        VI. "Kelebihan hari perjalanan Dinas sebanyak _____ hari (tgl _____ s/d _____)
        telah mendapat persetujuan / pengesahan saya"
      </td>
      <td style="text-align:center">PEJABAT YANG BERWENANG</td>
    </tr>
  </table>

  <div class="perhatian">
    <b>VII. PERHATIAN :</b><br>
    Pejabat yang berwenang menerbitkan SPPD, pegawai yang melakukan perjalanan dinas, para pejabat
    yang mengesahkan tanggal berangkat / tiba serta Bendaharawan bertanggung jawab berdasarkan
    Peraturan Perundang-undangan yang berlaku di Bank, apabila Bank menderita rugi akibat
    kelalaian, kesalahan dan kealpaan.
  </div>
</div>
</body>
</html>
