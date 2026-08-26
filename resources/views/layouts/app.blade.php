<!DOCTYPE html>
<html lang="id" data-tema="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('judul', 'HCIS') — Bank NTB Syariah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
{{-- Hanya bundel JS (SweetAlert2) — SENGAJA tidak menyertakan
     resources/css/app.css (Tailwind) di sini: seluruh tampilan saat
     ini memakai <style> inline per halaman, memuat reset Tailwind
     berisiko mengubah tampilan yang sudah berjalan di seluruh
     aplikasi tanpa perlu. --}}
@vite(['resources/js/app.js'])
<style>
/* Token desain — sumber tunggal warna & tipografi.
   Nilai identik dengan resources/css/tokens.css. */
:root{
  --hijau:#0A7A5C; --hijau-tua:#064E3B; --hijau-muda:#E6F2ED;
  --emas:#C9A227; --emas-muda:#FBF4DE;
  --putih:#fff; --latar:#F5F7F6; --garis:#E2E8E5;
  --teks:#0F1F1A; --teks-lemah:#5C706A;
  --merah:#B42318; --merah-muda:#FEF3F2;
  --r:10px;
  --lebar-sisi:230px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--latar);
  color:var(--teks);-webkit-font-smoothing:antialiased;line-height:1.5}
.angka{font-family:'JetBrains Mono',monospace;font-variant-numeric:tabular-nums}
a{color:inherit;text-decoration:none}
:focus-visible{outline:2px solid var(--emas);outline-offset:2px}

/* ---------- Kerangka: sidebar + konten ---------- */
.app-shell{display:flex;min-height:100vh;align-items:flex-start}
.sisi{width:var(--lebar-sisi);flex-shrink:0;background:var(--hijau-tua);color:#fff;
  min-height:100vh;display:flex;flex-direction:column;position:sticky;top:0}
.sisi .merek{display:flex;flex-direction:column;gap:10px;padding:18px 18px 16px}
.sisi .plat-logo{background:#fff;border-radius:8px;padding:9px 11px}
.sisi .plat-logo img{display:block;width:100%;height:auto}
.sisi .merek .jd{font-size:13.5px;font-weight:700;line-height:1.25}
.sisi .merek .sb{font-size:10px;opacity:.65}
.sisi nav{flex:1;overflow-y:auto;padding:6px 12px}
.grup{margin-top:16px}
.grup:first-child{margin-top:0}
.grup .label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
  opacity:.55;padding:7px 10px;cursor:pointer;list-style:none;display:flex;align-items:center;
  justify-content:space-between;gap:6px;border-radius:6px;user-select:none}
.grup .label:hover{background:rgba(255,255,255,.06)}
.grup .label::-webkit-details-marker{display:none}
.grup .label::after{content:'▾';font-size:9px;opacity:.7;transition:transform .12s}
.grup:not([open]) .label::after{transform:rotate(-90deg)}
.grup a, .grup .segera{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;
  font-size:12.8px;font-weight:600;color:rgba(255,255,255,.82);margin-bottom:2px;transition:.12s}
.grup a:hover{background:rgba(255,255,255,.1);color:#fff}
.grup a.aktif{background:rgba(255,255,255,.16);color:#fff}
.grup a .ic, .grup .segera .ic{width:16px;text-align:center;flex-shrink:0;opacity:.9}
.grup .segera{opacity:.4;cursor:default}
.grup .segera .tag{margin-left:auto;font-size:9px;font-weight:700;padding:2px 6px;border-radius:99px;
  background:rgba(255,255,255,.14)}
.grup a .lencana{margin-left:auto;flex-shrink:0;font-size:9.5px;font-weight:700;line-height:1;
  padding:3px 6px;border-radius:99px;background:var(--emas);color:#3a2e04}
.sisi .kaki{padding:14px 18px;border-top:1px solid rgba(255,255,255,.12)}
.sisi .kaki .nm{font-size:12px;font-weight:700}
.sisi .kaki .pr{font-size:10.5px;opacity:.65;margin-top:1px;margin-bottom:10px}
.sisi .kaki button{width:100%;background:rgba(255,255,255,.12);color:#fff;border:none;
  padding:8px;border-radius:7px;font-size:11.5px;font-weight:600;cursor:pointer;font-family:inherit}
.sisi .kaki button:hover{background:rgba(255,255,255,.2)}

/* Tombol buka/tutup sisi — hanya tampak di layar sempit (lihat media
   query di bawah). Checkbox tersembunyi + label sebagai tombol =
   toggle murni CSS, tidak perlu JavaScript. */
.sisi-toggle-input{position:absolute;opacity:0;pointer-events:none}
.sisi-toggle-tombol{display:none}

.app-main{flex:1;min-width:0}
.atas-halaman{background:var(--putih);border-bottom:1px solid var(--garis);
  padding:14px 22px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.atas-halaman .jd{font-size:15px;font-weight:700}
.atas-halaman .sb{font-size:11.5px;color:var(--teks-lemah);margin-top:1px}

.wadah{max-width:1180px;margin:0 auto;padding:22px 18px 60px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:16px;margin-bottom:13px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.pesan{padding:11px 14px;border-radius:9px;font-size:12.5px;font-weight:600;margin-bottom:15px}
.pesan.sukses{background:var(--hijau-muda);color:var(--hijau-tua);border:1px solid #BFDED2}
.pesan.gagal{background:var(--merah-muda);color:var(--merah);border:1px solid #F3C6C2}

.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;
  font-size:12.5px;font-weight:700;font-family:inherit;cursor:pointer;border:1px solid transparent;
  background:var(--hijau);color:#fff;transition:.15s}
.btn:hover{background:var(--hijau-tua)}
.btn.luar{background:var(--putih);color:var(--teks);border-color:var(--garis)}
.btn.luar:hover{background:var(--latar)}

.bidang{margin-bottom:16px}
.bidang label{display:block;font-size:12px;font-weight:600;color:var(--teks-lemah);margin-bottom:6px}
.bidang input,.bidang select,.bidang textarea{width:100%;padding:10px 12px;border:1px solid var(--garis);
  border-radius:8px;font-size:13.5px;font-family:inherit;color:var(--teks);background:var(--putih)}
.bidang input:focus,.bidang select:focus,.bidang textarea:focus{border-color:var(--hijau)}
.bidang .ket{font-size:11px;color:var(--teks-lemah);margin-top:5px}
.baris-bidang{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.baris-bidang{grid-template-columns:1fr}}

@media(max-width:860px){
  /* Default: mode ikon-saja — sisi TETAP di samping (flex-direction
     tetap row), BUKAN naik ke atas; lebar dipersempit supaya tidak
     memakan tempat konten. Nama modul tetap bisa dilihat kapan pun
     lewat tombol buka/tutup (☰) — sisi melebar penuh menampilkan
     label lengkap saat dibuka, TANPA menumpuk ke atas konten (masih
     flex-direction:row, konten cuma menyempit sementara). */
  .sisi{width:60px;transition:width .18s ease}
  .sisi:has(.sisi-toggle-input:checked){width:var(--lebar-sisi)}
  .sisi-toggle-tombol{display:flex;align-items:center;justify-content:center;
    width:100%;padding:12px 0;background:rgba(255,255,255,.08);border:none;
    border-bottom:1px solid rgba(255,255,255,.12);color:#fff;font-size:17px;
    cursor:pointer;flex-shrink:0}
  .sisi-toggle-tombol:hover{background:rgba(255,255,255,.16)}

  .sisi:not(:has(.sisi-toggle-input:checked)) .merek{padding:12px 6px;align-items:center}
  .sisi:not(:has(.sisi-toggle-input:checked)) .merek .jd,
  .sisi:not(:has(.sisi-toggle-input:checked)) .merek .sb{display:none}
  .sisi nav{padding:6px 6px}
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup .label{font-size:0;justify-content:center;padding:7px 4px}
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup .label::after{font-size:9px}
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup a,
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup .segera{font-size:0;justify-content:center;padding:9px 4px;position:relative}
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup a .ic,
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup .segera .ic{font-size:15px}
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup a .lencana{position:absolute;top:2px;right:2px;font-size:9px;margin-left:0}
  .sisi:not(:has(.sisi-toggle-input:checked)) .grup .segera .tag{display:none}
  .sisi:not(:has(.sisi-toggle-input:checked)) .kaki{padding:10px 4px}
  .sisi:not(:has(.sisi-toggle-input:checked)) .kaki .nm,
  .sisi:not(:has(.sisi-toggle-input:checked)) .kaki .pr{display:none}
  .sisi:not(:has(.sisi-toggle-input:checked)) .kaki button{font-size:0}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
@yield('gaya')
</style>
</head>
<body>

<div class="app-shell">
  <aside class="sisi">
    <input type="checkbox" id="sisi-toggle" class="sisi-toggle-input">
    <label for="sisi-toggle" class="sisi-toggle-tombol" aria-label="Buka/tutup menu">☰</label>
    <div class="merek">
      <div class="plat-logo">
        <img src="{{ asset('images/logo_ntbs-BSIF94NC.png') }}" alt="Bank NTB Syariah">
      </div>
      <div>
        <div class="jd">HCIS</div>
        <div class="sb">@yield('peran', 'Employee Self Service')</div>
      </div>
    </div>

    @auth
      @php
        $u = auth()->user();
        $peranAktif = $u->getRoleNames();
        $bukanAdminSistem = ! $u->hasRole('system_admin');
        $cocok = fn (string $nama) => request()->routeIs($nama) ? 'aktif' : '';
      @endphp

      <nav>
        @if ($bukanAdminSistem)
          <details class="grup" open>
            <summary class="label">Pegawai</summary>
            <a href="{{ route('ess.dashboard') }}" class="{{ request()->routeIs('ess.dashboard') ? 'aktif' : '' }}">
              <span class="ic">⌂</span> Beranda
            </a>
            <a href="{{ route('ess.cv') }}" class="{{ $cocok('ess.cv*') }}">
              <span class="ic">☰</span> CV Saya
            </a>
            <a href="{{ route('leave.create') }}" class="{{ $cocok('leave.*') }}">
              <span class="ic">▤</span> Ajukan Cuti
            </a>
            <a href="{{ route('overtime.create') }}" class="{{ $cocok('overtime.*') }}">
              <span class="ic">◷</span> Ajukan Lembur
            </a>
            <a href="{{ route('attendance.create') }}" class="{{ $cocok('attendance.create') }}">
              <span class="ic">◉</span> Absensi
            </a>
            <a href="{{ route('attendance.outside.create') }}" class="{{ $cocok('attendance.outside.*') }}">
              <span class="ic">⌖</span> Ajukan Absen Luar Kantor
            </a>
            <a href="{{ route('payslip.index') }}" class="{{ $cocok('payslip.*') }}">
              <span class="ic">§</span> Slip Gaji
            </a>
            <a href="{{ route('sppd.create') }}" class="{{ $cocok('sppd.*') }}">
              <span class="ic">✈</span> Ajukan SPPD
            </a>
            <a href="{{ route('shift.create') }}" class="{{ $cocok('shift.*') }}">
              <span class="ic">⇄</span> Ajukan Tukar Shift
            </a>
            <a href="{{ route('izin.create') }}" class="{{ $cocok('izin.*') }}">
              <span class="ic">✉</span> Ajukan Izin
            </a>
          </details>
        @endif

        @if ($bukanAdminSistem || $u->hasAnyRole(['hr_admin', 'hr_approver', 'system_admin']))
          <details class="grup" open>
            <summary class="label">LMS</summary>
            @if ($bukanAdminSistem)
              <a href="{{ route('lms.index') }}" class="{{ $cocok('lms.index') }}">
                <span class="ic">✎</span> Pelatihan
              </a>
              <a href="{{ route('lms.mine') }}" class="{{ $cocok('lms.mine') }}">
                <span class="ic">✎</span> Pelatihan Saya
              </a>
              <a href="{{ route('lms.library.index') }}" class="{{ $cocok('lms.library.*') }}">
                <span class="ic">☰</span> Perpustakaan Digital
              </a>
              <a href="{{ route('lms.development-plan') }}" class="{{ $cocok('lms.development-plan') }}">
                <span class="ic">↗</span> Rencana Pengembangan Saya
              </a>
              <a href="{{ route('lms.assessment.index') }}" class="{{ $cocok('lms.assessment.*') }}">
                <span class="ic">✓</span> Asesmen
              </a>
              <a href="{{ route('lms.leaderboard') }}" class="{{ $cocok('lms.leaderboard') }}">
                <span class="ic">🏆</span> Papan Peringkat
              </a>
              <a href="{{ route('lms.forum.index') }}" class="{{ $cocok('lms.forum.*') }}">
                <span class="ic">💬</span> Forum Diskusi
              </a>
              <a href="{{ route('lms.live-sessions.index') }}" class="{{ $cocok('lms.live-sessions.*') }}">
                <span class="ic">📅</span> Sesi Live/Mentoring
              </a>
            @endif
            @if ($u->hasAnyRole(['hr_admin', 'system_admin']) || $u->can('lms-catalog.manage'))
              <a href="{{ route('lms.admin.courses.index') }}" class="{{ $cocok('lms.admin.*') }}">
                <span class="ic">✎</span> Kelola Pelatihan
              </a>
            @endif
          </details>
        @endif

        @if ($u->hasAnyPermission(['overtime-approval.view', 'leave-approval.view', 'payroll-approval.manage', 'employee-approval.manage', 'sppd-approval.view', 'sppd-disbursement.hc.view', 'shift-swap-approval.view', 'lms-enrollment-approval.view', 'outside-attendance-approval.view', 'izin-approval.view']))
          <details class="grup" open>
            <summary class="label">Persetujuan</summary>
            @can('overtime-approval.view')
              <a href="{{ route('admin.approval-queue') }}" class="{{ $cocok('admin.approval-queue') }}">
                <span class="ic">✓</span> Antrean Lembur
                @isset($badgeCounts['admin.approval-queue'])<span class="lencana">{{ $badgeCounts['admin.approval-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('leave-approval.view')
              <a href="{{ route('admin.leave-approval-queue') }}" class="{{ $cocok('admin.leave-approval-queue') }}">
                <span class="ic">✓</span> Antrean Cuti
                @isset($badgeCounts['admin.leave-approval-queue'])<span class="lencana">{{ $badgeCounts['admin.leave-approval-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('payroll-approval.manage')
              <a href="{{ route('admin.payroll-approval-queue') }}" class="{{ $cocok('admin.payroll-approval-queue') }}">
                <span class="ic">✓</span> Persetujuan Payroll
                @isset($badgeCounts['admin.payroll-approval-queue'])<span class="lencana">{{ $badgeCounts['admin.payroll-approval-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('employee-approval.manage')
              <a href="{{ route('admin.employee-approval-queue') }}" class="{{ $cocok('admin.employee-approval-queue') }}">
                <span class="ic">✓</span> Persetujuan Data Pegawai
                @isset($badgeCounts['admin.employee-approval-queue'])<span class="lencana">{{ $badgeCounts['admin.employee-approval-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('sppd-approval.view')
              <a href="{{ route('admin.sppd-approval-queue') }}" class="{{ $cocok('admin.sppd-approval-queue') }}">
                <span class="ic">✓</span> Antrean SPPD
                @isset($badgeCounts['admin.sppd-approval-queue'])<span class="lencana">{{ $badgeCounts['admin.sppd-approval-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('sppd-disbursement.hc.view')
              <a href="{{ route('admin.sppd-disbursement-queue') }}" class="{{ $cocok('admin.sppd-disbursement-queue') }}">
                <span class="ic">$</span> Pencairan SPPD
                @isset($badgeCounts['admin.sppd-disbursement-queue'])<span class="lencana">{{ $badgeCounts['admin.sppd-disbursement-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('shift-swap-approval.view')
              <a href="{{ route('admin.shift-swap-queue') }}" class="{{ $cocok('admin.shift-swap-queue') }}">
                <span class="ic">⇄</span> Antrean Tukar Shift
                @isset($badgeCounts['admin.shift-swap-queue'])<span class="lencana">{{ $badgeCounts['admin.shift-swap-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('izin-approval.view')
              <a href="{{ route('admin.izin-queue') }}" class="{{ $cocok('admin.izin-queue') }}">
                <span class="ic">✉</span> Antrean Izin
                @isset($badgeCounts['admin.izin-queue'])<span class="lencana">{{ $badgeCounts['admin.izin-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('lms-enrollment-approval.view')
              <a href="{{ route('admin.lms-enrollment-queue') }}" class="{{ $cocok('admin.lms-enrollment-queue') }}">
                <span class="ic">✎</span> Antrean Pelatihan
                @isset($badgeCounts['admin.lms-enrollment-queue'])<span class="lencana">{{ $badgeCounts['admin.lms-enrollment-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('outside-attendance-approval.view')
              <a href="{{ route('admin.outside-attendance-queue') }}" class="{{ $cocok('admin.outside-attendance-queue') }}">
                <span class="ic">✓</span> Antrean Absen Luar Kantor
                @isset($badgeCounts['admin.outside-attendance-queue'])<span class="lencana">{{ $badgeCounts['admin.outside-attendance-queue'] }}</span>@endisset
              </a>
            @endcan
          </details>
        @endif

        @if ($u->hasRole('hr_admin') || $u->hasAnyPermission(['hc-dashboard.view', 'overtime-disbursement.hc', 'bekal-cuti-disbursement.hc']))
          <details class="grup" open>
            <summary class="label">SDM</summary>
            @can('hc-dashboard.view')
              <a href="{{ route('hc.dashboard') }}" class="{{ $cocok('hc.dashboard') }}">
                <span class="ic">▦</span> Dashboard HC
              </a>
            @endcan
            @role('hr_admin')
              <a href="{{ route('hr.employees') }}" class="{{ $cocok('hr.employees') }}">
                <span class="ic">☰</span> Data Pegawai
              </a>
            @endrole
            <a href="{{ route('sk.index') }}" class="{{ $cocok('sk.*') }}">
              <span class="ic">§</span> Surat Keputusan
            </a>
            @can('attendance-recap.view')
              <a href="{{ route('hr.attendance-recap') }}" class="{{ $cocok('hr.attendance-recap') }}">
                <span class="ic">◉</span> Rekap Absensi
              </a>
            @endcan
            <a href="{{ route('hr.overtime-recap') }}" class="{{ $cocok('hr.overtime-recap') }}">
              <span class="ic">Σ</span> Rekap Biaya Lembur
            </a>
            @role('hr_admin')
              <a href="{{ route('hr.payroll-deduction.index') }}" class="{{ $cocok('hr.payroll-deduction.*') }}">
                <span class="ic">−</span> Potongan Gaji
              </a>
              <a href="{{ route('hr.overtime-disbursement.index') }}" class="{{ $cocok('hr.overtime-disbursement.*') }}">
                <span class="ic">$</span> Pembayaran Lembur
                @isset($badgeCounts['hr.overtime-disbursement.index'])<span class="lencana">{{ $badgeCounts['hr.overtime-disbursement.index'] }}</span>@endisset
              </a>
              <a href="{{ route('hr.bekal-cuti.index') }}" class="{{ $cocok('hr.bekal-cuti.*') }}">
                <span class="ic">$</span> Pencairan Bekal Cuti
                @isset($badgeCounts['hr.bekal-cuti.index'])<span class="lencana">{{ $badgeCounts['hr.bekal-cuti.index'] }}</span>@endisset
              </a>
              <a href="{{ route('hr.sppd-disbursement.index') }}" class="{{ $cocok('hr.sppd-disbursement.*') }}">
                <span class="ic">$</span> Pencairan SPPD
                @isset($badgeCounts['hr.sppd-disbursement.index'])<span class="lencana">{{ $badgeCounts['hr.sppd-disbursement.index'] }}</span>@endisset
              </a>
            @endrole
            @can('overtime-disbursement.hc')
              <a href="{{ route('admin.overtime-disbursement-queue') }}" class="{{ $cocok('admin.overtime-disbursement-queue') }}">
                <span class="ic">$</span> Pembayaran Lembur (Kantor Pusat)
                @isset($badgeCounts['admin.overtime-disbursement-queue'])<span class="lencana">{{ $badgeCounts['admin.overtime-disbursement-queue'] }}</span>@endisset
              </a>
            @endcan
            @can('bekal-cuti-disbursement.hc')
              <a href="{{ route('admin.bekal-cuti-queue') }}" class="{{ $cocok('admin.bekal-cuti-queue') }}">
                <span class="ic">$</span> Pencairan Bekal Cuti (Bank-wide)
                @isset($badgeCounts['admin.bekal-cuti-queue'])<span class="lencana">{{ $badgeCounts['admin.bekal-cuti-queue'] }}</span>@endisset
              </a>
            @endcan
          </details>
        @endif

        @role('auditor')
          <details class="grup" open>
            <summary class="label">Pengawasan</summary>
            <a href="{{ route('audit.index') }}" class="{{ $cocok('audit.index') }}">
              <span class="ic">◎</span> Log Audit
            </a>
          </details>
        @endrole

        @if ($u->hasAnyRole(['system_admin', 'hr_approver']) || $u->hasAnyPermission(['sysadmin-content.manage', 'org-chart.view']))
          <details class="grup" open>
            <summary class="label">Administrasi Sistem</summary>
            {{-- Manajemen Pengguna & Peta Peran TETAP hardcode role
                 system_admin/hr_approver (kunci gerbang, sama seperti
                 middleware rutenya) — kapabilitas "kelola siapa-boleh-
                 apa" tidak boleh bisa mengunci-dirinya-sendiri lewat
                 sistem permission dinamis yang dia kelola sendiri. --}}
            @if ($u->hasAnyRole(['system_admin', 'hr_approver']))
              <a href="{{ route('sysadmin.users.index') }}" class="{{ $cocok('sysadmin.users.*') }}">
                <span class="ic">⚙</span> Manajemen Pengguna
              </a>
              <a href="{{ route('sysadmin.role-map.index') }}" class="{{ $cocok('sysadmin.role-map.*') }}">
                <span class="ic">?</span> Peta Peran
              </a>
            @endif
            @can('org-chart.view')
              <a href="{{ route('org-chart.index') }}" class="{{ $cocok('org-chart.*') }}">
                <span class="ic">⌂</span> Struktur Organisasi
              </a>
            @endcan
            @can('sysadmin-content.manage')
              <a href="{{ route('sysadmin.holidays.index') }}" class="{{ $cocok('sysadmin.holidays.*') }}">
                <span class="ic">▤</span> Kalender Hari Libur
              </a>
              <a href="{{ route('sysadmin.shift-patterns.index') }}" class="{{ $cocok('sysadmin.shift-patterns.*') }}">
                <span class="ic">⏱</span> Pola Shift
              </a>
              <a href="{{ route('sysadmin.shift-assignments.index') }}" class="{{ $cocok('sysadmin.shift-assignments.*') }}">
                <span class="ic">⇄</span> Penugasan Shift
              </a>
              <a href="{{ route('sysadmin.office-formasi.index') }}" class="{{ $cocok('sysadmin.office-formasi.*') }}">
                <span class="ic">Σ</span> Formasi Kantor
              </a>
              <a href="{{ route('sysadmin.offices.index') }}" class="{{ $cocok('sysadmin.offices.*') }}">
                <span class="ic">⌂</span> Daftar Kantor
              </a>
              <a href="{{ route('sysadmin.positions.index') }}" class="{{ $cocok('sysadmin.positions.*') }}">
                <span class="ic">☰</span> Daftar Jabatan
              </a>
              <a href="{{ route('sysadmin.journal-accounts.index') }}" class="{{ $cocok('sysadmin.journal-accounts.*') }}">
                <span class="ic">≡</span> Daftar Akun Jurnal
              </a>
            @endcan
          </details>
        @endif

        @role('system_admin')
          <details class="grup" open>
            <summary class="label">Administrasi Sistem (IT)</summary>
            <a href="{{ route('sysadmin.parameters.index') }}" class="{{ $cocok('sysadmin.parameters.*') }}">
              <span class="ic">≡</span> Konfigurasi Parameter
            </a>
            <a href="{{ route('sysadmin.salary-scale.index') }}" class="{{ $cocok('sysadmin.salary-scale.*') }}">
              <span class="ic">§</span> Skala Imbalan Kerja
            </a>
            <a href="{{ route('sysadmin.sppd-tariffs.index') }}" class="{{ $cocok('sysadmin.sppd-tariffs.*') }}">
              <span class="ic">✈</span> Tarif SPPD
            </a>
            <a href="{{ route('sysadmin.office-geofence.index') }}" class="{{ $cocok('sysadmin.office-geofence.*') }}">
              <span class="ic">◎</span> Titik Ordinat Kantor
            </a>
            <a href="{{ route('sysadmin.attendance-device.index') }}" class="{{ $cocok('sysadmin.attendance-device.*') }}">
              <span class="ic">▤</span> Impor Absensi Mesin
            </a>
            <a href="{{ route('sysadmin.employees.index') }}" class="{{ $cocok('sysadmin.employees.*') }}">
              <span class="ic">☰</span> Data Pegawai
            </a>
            <a href="{{ route('sysadmin.employees.import.index') }}" class="{{ $cocok('sysadmin.employees.import.*') }}">
              <span class="ic">⇈</span> Impor Pegawai
            </a>
            <a href="{{ route('sk.index') }}" class="{{ $cocok('sk.*') }}">
              <span class="ic">§</span> Surat Keputusan
            </a>
          </details>
        @endrole

        @if ($bukanAdminSistem)
          <details class="grup" open>
            <summary class="label">Segera Hadir</summary>
            <span class="segera"><span class="ic">★</span> KPI Pegawai <span class="tag">Segera</span></span>
          </details>
        @endif
      </nav>

      <div class="kaki">
        <div class="nm">{{ $u->employee?->full_name ?? $u->name }}</div>
        <div class="pr">{{ $peranAktif->map(fn ($r) => \App\Modules\Access\Domain\Role::tryFrom($r)?->label() ?? $r)->implode(' · ') ?: 'Tanpa peran' }}</div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit">Keluar</button>
        </form>
      </div>
    @endauth
  </aside>

  <div class="app-main">
    <header class="atas-halaman">
      <div>
        <div class="jd">@yield('judul', 'HCIS')</div>
      </div>
    </header>

    <div class="wadah">
      @if (session('sukses'))
        <div class="pesan sukses">{{ session('sukses') }}</div>
        <script>
          document.addEventListener('DOMContentLoaded', () => window.Swal?.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: @json(session('sukses')), showConfirmButton: false,
            timer: 4000, timerProgressBar: true,
          }));
        </script>
      @endif
      @if (session('gagal'))
        <div class="pesan gagal">{{ session('gagal') }}</div>
        <script>
          document.addEventListener('DOMContentLoaded', () => window.Swal?.fire({
            toast: true, position: 'top-end', icon: 'error',
            title: @json(session('gagal')), showConfirmButton: false,
            timer: 5000, timerProgressBar: true,
          }));
        </script>
      @endif

      @yield('isi')
    </div>
  </div>
</div>

@yield('skrip')
</body>
</html>
