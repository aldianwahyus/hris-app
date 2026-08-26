@extends('layouts.app')

@section('judul', 'Beranda Pegawai')
@section('peran', 'Employee Self Service')

@section('gaya')
.atas{background:var(--hijau-tua);color:#fff;border-radius:var(--r);padding:18px 20px;margin-bottom:14px}
.atas .nm{font-size:17px;font-weight:700}
.atas .jb{font-size:12px;opacity:.75;margin-top:3px}
.kisi{display:grid;grid-template-columns:1.35fr 1fr;gap:14px}
@media(max-width:860px){.kisi{grid-template-columns:1fr}}

/* Kantong saldo cuti — elemen utama layar ini */
.kantong{display:flex;flex-direction:column;gap:15px}
.kt .brs{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px}
.kt .nm{font-size:13px;font-weight:600}
.kt .ket{font-size:10.5px;color:var(--teks-lemah);margin-top:1px}
.kt .ang{font-size:14px;font-weight:700}
.kt .ang span{font-size:11px;font-weight:500;color:var(--teks-lemah)}
.rel{height:7px;background:var(--latar);border-radius:99px;overflow:hidden;display:flex}
.rel i{height:100%}
.rel i.pakai{background:var(--hijau)}
.rel i.sisa{background:#CFE3DA}
.rel.mati i.sisa{background:#E5EAE8}
.tag{display:inline-block;font-size:9.5px;font-weight:700;padding:2px 8px;
  border-radius:99px;margin-top:7px}
.tag.ya{background:var(--emas-muda);color:#7A5F0B;border:1px solid #E8D9A0}
.tag.tidak{background:var(--latar);color:var(--teks-lemah);border:1px solid var(--garis)}
.info{margin-top:14px;padding:11px 13px;background:var(--emas-muda);
  border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6}

.baris-item{display:flex;gap:11px;align-items:flex-start;padding:11px;
  border:1px solid var(--garis);border-radius:9px;margin-bottom:8px}
.baris-item .j{font-size:12.5px;font-weight:600}
.baris-item .s{font-size:11px;color:var(--teks-lemah);margin-top:2px}
.status{font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}
.status.pending, .status.pending_pimpinan{background:var(--emas-muda);color:#7A5F0B}
.status.approved{background:var(--hijau-muda);color:var(--hijau-tua)}
.status.rejected, .status.expired{background:var(--merah-muda);color:var(--merah)}
.status.disbursed{background:#DCEEFF;color:#0B5FA5}
.kosong{font-size:12px;color:var(--teks-lemah);padding:14px 0;text-align:center}
@endsection

@section('isi')
<div class="atas">
  <div class="nm">{{ $employee->full_name }}</div>
  <div class="jb">
    {{ $employee->position_name }} · {{ $employee->office_name }} · PG {{ $employee->person_grade }}
    · NRP {{ $employee->nrp }}
  </div>
</div>

<div class="kisi">
  <div>
    <div class="kartu">
      <div class="kartu-judul">Saldo cuti {{ date('Y') }}</div>
      <div class="kantong">
        @foreach ($balances as $b)
          @php
            $sisa = $b->quota_days - $b->used_days;
            $persenPakai = $b->quota_days > 0 ? ($b->used_days / $b->quota_days) * 100 : 0;
            $nama = match ($b->bucket_type) {
              'current_year' => 'Jatah tahun berjalan',
              'carry_forward' => 'Sisa tahun ' . ($b->year - 1),
              'long_leave' => 'Cuti besar',
              default => $b->bucket_type,
            };
          @endphp
          <div class="kt">
            <div class="brs">
              <div>
                <div class="nm">{{ $nama }}</div>
                <div class="ket">
                  @if ($b->bucket_type === 'carry_forward')
                    Hangus setelah {{ date('j F Y', strtotime($b->expires_on)) }}
                  @else
                    Berlaku sampai {{ date('j F Y', strtotime($b->expires_on)) }}
                  @endif
                </div>
              </div>
              <div class="ang angka">{{ rtrim(rtrim(number_format($sisa, 1, ',', '.'), '0'), ',') }}
                <span>/ {{ (int) $b->quota_days }}</span>
              </div>
            </div>
            <div class="rel">
              <i class="pakai" style="width:{{ $persenPakai }}%"></i>
              <i class="sisa" style="width:{{ 100 - $persenPakai }}%"></i>
            </div>
            @if ($b->triggers_allowance)
              <span class="tag ya">Memberi hak bekal cuti</span>
            @else
              <span class="tag tidak">Tidak memberi hak bekal cuti</span>
            @endif
          </div>
        @endforeach

        @if ($longLeaveYear)
          <div class="kt">
            <div class="brs">
              <div>
                <div class="nm">Cuti besar</div>
                <div class="ket">
                  @if ($longLeaveYear <= (int) date('Y'))
                    Hak sudah terbuka
                  @else
                    Terbuka pada {{ $longLeaveYear }} · 6 tahun sejak pengangkatan tetap
                  @endif
                </div>
              </div>
              <div class="ang angka">{{ $longLeaveYear <= (int) date('Y') ? '22' : '—' }}</div>
            </div>
            <div class="rel mati"><i class="sisa" style="width:100%"></i></div>
          </div>
        @endif
      </div>

      <div class="info">
        Bekal cuti dibayar <strong>satu kali setiap tahun</strong>, pada saat Anda mengambil
        jatah tahun berjalan untuk pertama kalinya. Memakai sisa cuti tahun lalu tidak
        memicu pembayaran.
      </div>
    </div>
  </div>

  <div>
    <div class="kartu">
      <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-bottom:13px">
        <div class="kartu-judul" style="margin-bottom:0">Pengajuan cuti</div>
        <a href="{{ route('leave.create') }}" class="btn" style="padding:6px 12px">Ajukan Cuti</a>
      </div>
      @forelse ($requests as $r)
        <div class="baris-item">
          <div style="flex:1">
            <div class="j">{{ $r->leave_type === 'CT' ? 'Cuti tahunan' : $r->leave_type }} — {{ rtrim(rtrim(number_format($r->total_days, 1, ',', '.'), '0'), ',') }} hari</div>
            <div class="s angka">{{ date('j M', strtotime($r->start_date)) }}–{{ date('j M Y', strtotime($r->end_date)) }}</div>
          </div>
          <span class="status {{ $r->status }}">
            {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$r->status] ?? $r->status }}
          </span>
        </div>
      @empty
        <div class="kosong">Belum ada pengajuan cuti.</div>
      @endforelse
    </div>

    <div class="kartu">
      <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-bottom:13px">
        <div class="kartu-judul" style="margin-bottom:0">Pengajuan lembur</div>
        <a href="{{ route('overtime.create') }}" class="btn" style="padding:6px 12px">Ajukan Lembur</a>
      </div>
      @forelse ($overtime as $o)
        <div class="baris-item">
          <div style="flex:1">
            <div class="j">
              {{ $o->overtime_type === 'crash_program' ? 'Crash program' : 'Lembur biasa' }}
              — {{ date('j M Y', strtotime($o->work_date)) }}
            </div>
            <div class="s">
              <span class="angka">{{ rtrim(rtrim(number_format($o->payable_hours, 1, ',', '.'), '0'), ',') }} jam</span>
              · {{ $o->spkl_number }}
            </div>
          </div>
          <span class="status {{ $o->status }}">
            {{ [
              'pending' => 'Menunggu',
              'pending_pimpinan' => 'Menunggu Pimpinan',
              'approved' => 'Disetujui',
              'rejected' => 'Ditolak',
              'expired' => 'Kedaluwarsa',
              'disbursed' => 'Sudah Dicairkan',
            ][$o->status] ?? $o->status }}
          </span>
          @if (in_array($o->status, ['approved', 'disbursed'], true))
            <a href="{{ route('overtime.spkl', $o->id) }}" class="btn" style="padding:5px 10px;font-size:11.5px">Cetak SPKL</a>
          @endif
        </div>
      @empty
        <div class="kosong">Belum ada pengajuan lembur.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
