<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $documentTypeLabel }} — {{ $employee->full_name }}</title>
<style>
  body{font-family:'Helvetica',Arial,sans-serif;font-size:12px;color:#1a1a1a;margin:32px}
  .kop{text-align:center;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:18px}
  .kop img{height:34px}
  .kop p{margin:2px 0 0;font-size:11px;color:#444}
  .judul{text-align:center;margin-bottom:4px}
  .judul h2{font-size:14px;text-decoration:underline;margin:0;letter-spacing:.02em}
  .judul p{margin:2px 0 0;font-size:11px}
  table.data{width:100%;border-collapse:collapse;margin:18px 0}
  table.data td{padding:4px 6px;vertical-align:top;font-size:11.5px}
  table.data td.label{width:150px;color:#333}
  table.data td.sep{width:14px}
  .isi{margin:16px 0;font-size:11.5px;line-height:1.8;text-align:justify}
  .ttd{width:100%;margin-top:40px}
  .ttd table{width:100%}
  .ttd td{width:50%;text-align:center;font-size:11.5px;vertical-align:top}
  .ttd .garis{margin-top:56px;border-top:1px solid #1a1a1a;padding-top:4px;display:inline-block;min-width:200px}
  .ttd-elektronik{margin-top:8px;font-size:9.5px;color:#1a7a3a;line-height:1.5}
  .catatan{margin-top:24px;font-size:10px;color:#666}
</style>
</head>
<body>
  <div class="kop">
    <img src="{{ \App\Interfaces\Http\Support\CompanyProfile::logoDataUri() }}" alt="Bank NTB Syariah">
    <p>Kantor Pusat — Jl. Pejanggik No. 30 Mataram</p>
  </div>

  <div class="judul">
    <h2>{{ strtoupper($documentTypeLabel) }}</h2>
    <p>Nomor: DOK/{{ strtoupper(substr($row->id, 0, 8)) }}</p>
  </div>

  <table class="data">
    <tr><td class="label">Nama Pegawai</td><td class="sep">:</td><td>{{ $employee->full_name }}</td></tr>
    <tr><td class="label">NRP</td><td class="sep">:</td><td>{{ $employee->nrp }}</td></tr>
    <tr><td class="label">Unit Kerja</td><td class="sep">:</td><td>{{ $employee->office_name }}</td></tr>
    <tr><td class="label">Bergabung Sejak</td><td class="sep">:</td><td>{{ \Carbon\Carbon::parse($employee->join_date)->translatedFormat('d F Y') }}</td></tr>
  </table>

  <div class="isi">
    @if ($row->document_type === 'surat_keterangan_kerja')
      Dengan ini menerangkan bahwa pegawai yang tersebut identitasnya di atas benar merupakan
      pegawai aktif Bank NTB Syariah sejak tanggal yang tercantum di atas. Surat keterangan ini
      diterbitkan untuk keperluan: <strong>{{ $row->purpose }}</strong>.
    @elseif ($row->document_type === 'surat_referensi')
      Dengan ini menerangkan bahwa pegawai yang tersebut identitasnya di atas telah bekerja di
      Bank NTB Syariah dengan penilaian kinerja yang baik selama masa kerjanya. Surat referensi
      ini diterbitkan untuk keperluan: <strong>{{ $row->purpose }}</strong>.
    @elseif ($row->document_type === 'surat_keterangan_penghasilan')
      Dengan ini menerangkan bahwa pegawai yang tersebut identitasnya di atas benar merupakan
      pegawai aktif Bank NTB Syariah dan menerima penghasilan sesuai dengan ketentuan penggajian
      yang berlaku di Bank NTB Syariah. Rincian penghasilan dapat diperoleh melalui unit kerja
      Sumber Daya Manusia. Surat keterangan ini diterbitkan untuk keperluan:
      <strong>{{ $row->purpose }}</strong>.
    @else
      Dengan ini menerangkan bahwa pegawai yang tersebut identitasnya di atas benar merupakan
      pegawai aktif Bank NTB Syariah. Surat keterangan ini diterbitkan untuk keperluan:
      <strong>{{ $row->purpose }}</strong>.
    @endif
  </div>

  <div class="isi">
    Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
  </div>

  <div class="ttd">
    <table>
      <tr>
        <td></td>
        <td>
          Mataram, {{ \Carbon\Carbon::parse($row->processed_at ?? now())->translatedFormat('d F Y') }}<br>
          Sumber Daya Manusia,<br>
          @if ($signature)
            <span class="garis">{{ $signature->signer_name_snapshot }}</span>
            <div class="ttd-elektronik">
              Ditandatangani secara elektronik pada {{ \Carbon\Carbon::parse($signature->signed_at)->translatedFormat('d F Y, H:i') }} WITA
              — Kode verifikasi: {{ substr($signature->document_hash, 0, 8) }}
            </div>
          @else
            <span class="garis">&nbsp;</span>
          @endif
        </td>
      </tr>
    </table>
  </div>

  <p class="catatan">
    Dokumen ini dicetak otomatis oleh sistem HCIS pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WITA.
  </p>
</body>
</html>
