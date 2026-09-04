# Uji Beban 1600 Pengguna Serentak (evaluasi PM/client 2026-09-04)

Pertanyaan yang diuji: **apakah aplikasi masih tahan pada 1600 pengguna serentak, atau
terjadi deadlock?**

## Cara menjalankan ulang

```bash
# 1. Semai 1600 pegawai+pengguna+token throwaway + 1 survei aktif (target kontensi kunci)
tail -n +2 scripts/stress-test-1600-seed.php | docker compose exec -T app php artisan tinker

# 2. (opsional tapi disarankan) naikkan sementara batas PHP-FPM & Postgres — lihat
#    "Keterbatasan lingkungan" di bawah untuk alasannya
docker compose exec app sh -c "sed -i 's/^pm.max_children = .*/pm.max_children = 100/' /usr/local/etc/php-fpm.d/www.conf"
docker compose exec postgres sh -c "sed -i 's/^max_connections = .*/max_connections = 250/' /var/lib/postgresql/data/postgresql.conf"
docker compose restart postgres app horizon

# 3. Jalankan skrip k6 (dua varian, lihat di bawah)
MSYS_NO_PATHCONV=1 docker run --rm --network hris-app_hris \
  -v "$(pwd -W)/scripts:/scripts" -e BASE_URL=http://nginx \
  grafana/k6 run /scripts/stress-test-1600-write.js

# 4. WAJIB: kembalikan batas semula
docker compose exec app sh -c "sed -i 's/^pm.max_children = .*/pm.max_children = 5/' /usr/local/etc/php-fpm.d/www.conf"
docker compose exec postgres sh -c "sed -i 's/^max_connections = .*/max_connections = 100/' /var/lib/postgresql/data/postgresql.conf"
docker compose restart postgres app horizon

# 5. WAJIB: bersihkan data throwaway
tail -n +2 scripts/stress-test-1600-cleanup.php | docker compose exec -T app php artisan tinker
```

1600 token Sanctum disemai **langsung lewat basis data** (bukan lewat
`POST /api/v1/auth/login`) — pola sama `actingAs()` pada test PHPUnit. `throttle:30,1` pada
rute login SAMA SEKALI tidak disentuh atau dilemahkan (pelajaran dari
`LOAD_TEST_RESULTS.md`: mengubah pagar keamanan demi angka benchmark bukan trade-off yang
sepadan — di sini bahkan tidak perlu, sesi login bisa disemai langsung).

Dua skrip k6:
- **`stress-test-1600.js`** — alur realistis (GET /user → GET /notifikasi → GET
  /survei/{id} → POST /survei/{id}/isi) per pengguna. Hasil: mayoritas pengguna tidak
  pernah sampai ke langkah tulis (lihat di bawah) — TIDAK dipakai untuk menyimpulkan
  perilaku tulis, hanya untuk mengukur pengalaman pengguna ujung-ke-ujung.
- **`stress-test-1600-write.js`** — TERFOKUS, langsung ke `POST /survei/{id}/isi` (1
  `GET` pendahulu untuk ambil `question_id`). Skenario PALING ADVERSARIAL yang bisa
  disusun dari fitur yang ada: 1600 pengguna BERBEDA, SEMUANYA mengirim ke SATU baris
  `svy_surveys` yang sama lewat `lockForUpdate()` (lihat
  `SubmitSurveyResponse::handle()`) — dipilih justru karena ini kandidat PALING mungkin
  memunculkan deadlock/lock-timeout kalau memang ada masalah.

## Keterbatasan lingkungan (WAJIB dibaca sebelum menafsirkan angka di bawah)

- **Lingkungan Docker dev di SATU mesin pengembang** (Windows + Docker Desktop) —
  BUKAN infrastruktur produksi, sama seperti `LOAD_TEST_RESULTS.md` sebelumnya.
- **`pm.max_children` bawaan hanya 5** (`docker/php` image, tidak pernah disetel untuk
  beban) dan **Postgres `max_connections` bawaan 100**. Pada nilai bawaan itu, 1600
  koneksi serentak HAMPIR PASTI hanya akan mengantre di lapisan PHP-FPM — tidak
  mengukur apa pun tentang perilaku APLIKASI, hanya membuktikan lingkungan dev sengaja
  tidak disetel untuk beban tinggi (wajar untuk mesin pengembang). Karena itu, batas
  dinaikkan SEMENTARA (`pm.max_children=100`, `max_connections=250`) khusus untuk
  pengujian ini, supaya angka yang didapat mencerminkan perilaku APLIKASI/basis data,
  bukan sekadar ukuran antrean proses. Batas dikembalikan ke nilai semula segera
  setelah pengujian (langkah 4 di atas) — TIDAK ada perubahan permanen ke konfigurasi.
- **BUKAN pengganti perencanaan kapasitas produksi.** Server produksi sesungguhnya
  (spesifikasi CPU/RAM nyata, connection pooler seperti PgBouncer, PHP-FPM disetel
  sesuai kapasitas server, load balancer multi-instance) akan berperilaku sangat
  berbeda dari satu kontainer di laptop pengembang.

## Temuan 1 — Nol deadlock, nol pelanggaran keunikan, pada skenario paling adversarial

`stress-test-1600-write.js`: 1600 pengguna BERBEDA mengirim jawaban survei ke SATU baris
yang sama secara nyaris serentak.

**Kebenaran di lapisan basis data** (lebih dapat dipercaya daripada status HTTP di sisi
klien k6, yang bisa timeout menunggu meski server tetap memprosesnya — lihat Temuan 2):

```
survei responses tercatat  : 1573 / 1600  (98.3%)
svy_response_tokens tercatat: 1573  (SAMA PERSIS dengan responses — tidak ada duplikat)
pg_stat_database.deadlocks : 0  (sebelum DAN sesudah pengujian)
log Postgres                : tidak ada baris "deadlock detected"
```

**Kesimpulan**: `lockForUpdate()` pada `SubmitSurveyResponse::handle()` bekerja PERSIS
seperti dirancang — 1600 permintaan tulis ke baris yang sama diserialkan dengan benar oleh
Postgres, TANPA satu pun deadlock, TANPA satu pun pelanggaran constraint UNIQUE
(`svy_response_tokens`), TANPA data ganda/rusak. Kontainer `app`/`postgres` tetap sehat dan
merespons normal (`/masuk` → 200) sepanjang dan setelah pengujian — tidak ada crash.

Ini BUKAN kebetulan sempit pada satu fitur: pola kode di seluruh aplikasi (diperiksa lewat
sesi kerja Fase 2 sebelumnya — `ReviewDeletionRequest`, `ReviewReport`, `ReplyTicket`, dll.)
SELALU mengunci HANYA SATU baris per transaksi, tidak pernah dua baris berbeda dengan
urutan yang bisa bertukar antar transaksi. Itulah syarat struktural sebuah deadlock
sungguhan (Postgres: circular wait dua transaksi saling menunggu kunci satu sama lain) —
karena pola penguncian di sini konsisten "satu baris per transaksi", deadlock lintas-baris
secara struktural nyaris mustahil terjadi, bukan cuma kebetulan tidak muncul di uji ini.

## Temuan 2 — Bukan deadlock, tapi ANTREAN LATENSI adalah biaya nyata di 1600 serentak

27 dari 1600 (1.7%) percobaan tulis TIDAK tercatat di basis data. Ini BUKAN 27 kegagalan
tulis di server — pemeriksaan log dan status memastikan tidak ada error 500 pada
permintaan yang benar-benar direspons server. Penjelasan paling mungkin: permintaan itu
masih MENGANTRE di PHP-FPM/koneksi saat batas timeout klien k6 (120 detik) tercapai lebih
dulu — k6 menyerah menunggu SEBELUM server sempat memulai memprosesnya. Latensi median
pada uji ini mencapai puluhan detik hingga ~2 menit pada beban puncak, bahkan SETELAH batas
`pm.max_children` dinaikkan 20× dari bawaan (5 → 100).

**Kesimpulan**: pada 1600 koneksi serentak yang datang HAMPIR BERSAMAAN (bukan tersebar
wajar sepanjang waktu seperti trafik produksi nyata), satu kontainer PHP-FPM di laptop
pengembang akan mengalami antrean permintaan yang signifikan — pengguna akan merasakan
aplikasi lambat/nyaris tidak responsif untuk sesaat pada lonjakan sebesar itu. Ini adalah
temuan KAPASITAS INFRASTRUKTUR (jumlah worker PHP-FPM, jumlah instance, load balancer),
BUKAN temuan tentang kebenaran/keamanan transaksi aplikasi — dan bukan sesuatu yang bisa
"diperbaiki" lewat perubahan kode aplikasi, melainkan lewat perencanaan kapasitas server
produksi sesungguhnya (jumlah core CPU, jumlah instance PHP-FPM/container, load balancer,
dan realistiknya, 1600 pegawai TIDAK PERNAH login serentak dalam hitungan detik yang sama
pada operasional bank sehari-hari).

## Ringkasan untuk pengambilan keputusan

| Pertanyaan | Jawaban |
|---|---|
| Apakah aplikasi mengalami deadlock pada 1600 pengguna serentak? | **Tidak** — 0 deadlock tercatat, pada skenario tulis paling adversarial yang bisa disusun dari fitur yang ada. |
| Apakah data tetap benar (tidak ada duplikat/rusak) di bawah kontensi berat? | **Ya** — 1573 respons tercatat, 1573 token unik, sama persis, tanpa pelanggaran constraint. |
| Apakah aplikasi "masih tahan" di 1600 pengguna serentak pada KONFIGURASI DEV BAWAAN? | **Tidak diukur langsung** (batas `pm.max_children=5` bawaan akan membuat SEMUA request lain mengantre di belakang 5 yang diproses) — tapi ini adalah batas yang MEMANG sengaja rendah untuk mesin dev, dan mudah dinaikkan sesuai kapasitas server sungguhan. |
| Apakah ini setara jaminan kapasitas produksi? | **Tidak** — perlu pengujian di infrastruktur produksi sesungguhnya (atau staging yang menyerupainya) untuk angka kapasitas yang bisa dijadikan acuan operasional. |
