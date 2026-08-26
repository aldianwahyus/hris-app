# Enterprise HRIS — PT Bank NTB Syariah

**Sprint 0 — Kerangka Fondasi**

Repositori ini memuat kerangka teknis awal sesuai ARCH-001 (System Architecture) dan DB-001 (Database Design). Sprint 0 **sengaja tidak memuat aturan bisnis** apa pun, sehingga aman dikerjakan meskipun sebagian requirement (tabel gaji, daftar kantor) belum diterima.

---

## Apa yang sudah ada

| Komponen | Isi | Status |
|---|---|---|
| `app/Core/Domain` | `Uuid7`, `Money`, `AggregateRoot`, `DomainEvent` | ✅ Teruji |
| `app/Shared/Temporal` | `EffectivePeriod`, `AsOfDate` — pola effective dating | ✅ |
| `tests/Architecture` | Penegakan batas modul (5 aturan) | ✅ Terbukti menangkap pelanggaran |
| `database/migrations` | Audit trail (partisi), konfigurasi berversi, seed parameter | ✅ |
| `docker/` | PHP 8.4, Nginx, PostgreSQL 16, Redis, MinIO, Horizon | ✅ |
| `.github/workflows` | CI: arsitektur → kualitas → uji | ✅ |

## Menjalankan

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Aplikasi: `http://localhost:8080` · MinIO Console: `http://localhost:9001`

## Perintah

```bash
composer test          # seluruh uji
composer test:arch     # HANYA uji batas modul
composer analyse       # PHPStan level 8
composer format        # Laravel Pint
composer check         # format + analisis + uji
```

---

## Aturan arsitektur yang ditegakkan otomatis

`tests/Architecture/ModuleBoundaryTest.php` **menggagalkan CI** bila dilanggar:

| Aturan | Penjelasan |
|---|---|
| **M-1 / M-2** | Modul hanya mengakses modul lain melalui `Contracts/`. Akses ke `Domain/`, `Application/`, `Infrastructure/` modul lain dilarang. |
| **Lapisan** | `Domain/` tidak boleh mengimpor Illuminate, Infrastructure, atau Interfaces. Aturan bisnis harus dapat diuji tanpa basis data. |
| **M-3** | Tidak boleh ada ketergantungan melingkar antar modul. |
| **Shared/Core** | Tidak boleh bergantung pada modul bisnis. Bila terjadi, komponen itu sesungguhnya milik satu modul. |
| **Uang** | Properti bernuansa nominal tidak boleh bertipe `float`. Gunakan `Money`. |

> **Mengapa otomatis, bukan konvensi.** Tanpa pagar di CI, batas modul pada modular monolith luntur dalam hitungan bulan. Bila itu terjadi, syarat Project Constitution — setiap modul dapat dipisahkan menjadi microservice — menjadi mustahil dipenuhi.

---

## Keputusan teknis & alasannya

**UUID v7, bukan v4.** UUID v4 acak sehingga memfragmentasi indeks B-Tree dan memperlambat penulisan pada tabel bervolume tinggi (absensi ±1.000 baris/hari). UUID v7 menempatkan timestamp pada 48 bit pertama sehingga sisipan mendekati berurutan. Terverifikasi pada `Uuid7Test`.

**`Money` berbasis sen, bukan float.** Sistem ini menghitung gaji, Bekal Cuti (1–2× gaji bulanan × 1.000+ pegawai), dan PPh 21. Kesalahan pembulatan tidak dapat diterima.

**Konfigurasi berversi bertanggal berlaku.** Aturan bisnis Bank berubah melalui SK Direksi — sudah terjadi berulang. Setiap parameter menyimpan `effective_from`/`effective_to` dan nomor SK. *Uji penerimaan:* menghitung ulang upah lembur Mei 2026 harus menghasilkan angka identik meski tarif berubah 1 Juli 2026.

**Audit trail append-only & terpartisi.** `REVOKE UPDATE, DELETE` di tingkat basis data. Partisi bulanan ditetapkan sejak awal — mengonversi tabel berisi jutaan baris kemudian memerlukan downtime panjang.

**`AsOfDate` sebagai tipe wajib.** Pembacaan entitas temporal wajib menyertakan tanggal acuan. Dengan menjadikannya tipe (bukan parameter opsional), kelalaian tertangkap saat analisis statis — bukan saat audit.

---

## Struktur

```
app/
├── Core/          Kernel: Uuid7, Money, AggregateRoot, DomainEvent
├── Shared/        Kapabilitas lintas modul
│   ├── Temporal/       effective dating
│   ├── Audit/          jejak audit
│   ├── Configuration/  parameter berversi
│   ├── Workflow/       3 pola approval
│   ├── Taxation/       Tax Ledger (Wave 3)
│   ├── Payment/        instruksi pembayaran (Wave 3)
│   └── ExpenseClaim/   klaim berbukti (Wave 2–4)
├── Modules/       Modul bisnis (lihat ARCH-001 §4.4)
└── Interfaces/    HTTP API, Web, Console
```

Setiap modul: `Domain/` · `Application/` · `Infrastructure/` · `Interfaces/` · `Contracts/`
Hanya `Contracts/` yang boleh diakses modul lain.

---

## Yang BELUM dapat dibangun

| Terblokir | Penyebab |
|---|---|
| Payroll Engine | Tabel Skala Imbalan Kerja (Lampiran II, 35×19) belum diterima |
| Komponen tunjangan | Tabel Tunjangan (Lampiran III) belum diterima |
| Geofencing absensi | Daftar kantor + koordinat belum ada |
| Modul Absensi (rilis) | Kajian UU PDP untuk biometrik belum dimulai |
| Sizing infrastruktur | Jumlah pegawai & kantor belum diketahui |

Rujukan: GAP-001 Readiness Assessment.

---

## Dokumen terkait

| Dokumen | Isi |
|---|---|
| RA-001 v0.22 | Requirement Analysis — 101 keputusan (DEC) |
| ARCH-001 v1.0 | System Architecture |
| DB-001 v1.0 | Database Design |
| GAP-001 | Readiness Assessment |

**CONFIDENTIAL** — milik PT Bank NTB Syariah.
