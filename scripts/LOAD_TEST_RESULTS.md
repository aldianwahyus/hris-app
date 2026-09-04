# Hasil Load Test — Fase 2 (evaluasi PM/client 2026-09-03)

## Cara menjalankan

```bash
docker run --rm --network hris-app_hris \
  -v /path/ke/proyek/scripts:/scripts \
  -e BASE_URL=http://nginx \
  grafana/k6 run /scripts/load-test.js
```

Dijalankan lewat image resmi `grafana/k6`, terpasang ke jaringan Docker Compose proyek ini
(`hris-app_hris`) supaya bisa menjangkau `nginx` langsung dari dalam jaringan internal — tidak
lewat port host `8080`, dan tidak butuh instalasi k6 di mesin pengembang.

## Keterbatasan (WAJIB dibaca sebelum menafsirkan angka di bawah)

- **Ini mengukur lingkungan Docker dev di SATU mesin pengembang** (Windows + Docker Desktop),
  bukan infrastruktur produksi. Sumber daya CPU/RAM yang dialokasikan Docker Desktop di sini
  jauh dari representatif dibanding server produksi sesungguhnya.
- **Data seed dev**, bukan volume data produksi (jumlah pegawai/transaksi jauh lebih kecil).
- **Cakupan sengaja dibatasi ke halaman PUBLIK** (`/masuk`, `/captcha-refresh`, `/lowongan`) —
  TIDAK menguji halaman yang perlu login. Halaman `/masuk` mewajibkan captcha
  (`mews/captcha`, lihat `LoginRequest`) yang tidak bisa diselesaikan skrip otomatis tanpa
  membuka jalur pintas keamanan baru — menambah bypass captcha demi angka load test bukan
  trade-off yang sepadan di sini, jadi TIDAK dilakukan.
- **BUKAN pengganti perencanaan kapasitas produksi** — untuk itu, kapasitas server produksi
  sesungguhnya dan pola trafik nyata (bukan simulasi) yang harus jadi rujukan.

## Temuan utama: rate limiter, BUKAN kapasitas aplikasi, yang jadi faktor dominan

Percobaan pertama (100 VU puncak, 3.5 menit) menunjukkan **tingkat gagal 94.74%** dan
**p95 latency 11.29 detik** — angka yang secara sepintas terlihat seperti aplikasi tidak
sanggup menangani beban. Diagnosis lanjutan (10 VU, 15 detik, hanya `/masuk`) menunjukkan
akar penyebab sesungguhnya:

```
31 x status=200
116 x status=429
```

**116 dari 147 permintaan (79%) menerima HTTP 429 Too Many Requests** — bukan error 500 atau
timeout. Ini adalah middleware `throttle:30,1` pada rute `/masuk` (lihat `routes/web.php`)
BEKERJA SESUAI RANCANGAN: seluruh trafik k6 berasal dari SATU alamat IP (kontainer k6 itu
sendiri), sehingga langsung melampaui ambang 30 permintaan/menit per-IP dalam hitungan detik
— persis skenario yang dirancang middleware ini untuk dicegah (lihat komentar kode: "murni
pagar tambahan, mis. scraping halaman masuk").

**Kesimpulan**: hasil load test ini TIDAK membuktikan aplikasi lambat/tidak sanggup menangani
beban — ia membuktikan pagar keamanan anti-flood bekerja dengan benar terhadap pola trafik
bersumber tunggal. Trafik produksi sesungguhnya datang dari BANYAK alamat IP pegawai berbeda,
sehingga tidak akan memicu ambang per-IP ini seperti yang terjadi pada uji bersumber tunggal
ini.

## Yang TIDAK dikerjakan (dan alasannya)

Pengukuran "kapasitas mentah tanpa pagar keamanan" — dengan menaikkan/menonaktifkan
`throttle:30,1` sementara — SENGAJA tidak dilakukan di sesi ini. Mengubah middleware keamanan
produksi (walau sementara) demi angka benchmark berisiko lupa dikembalikan atau salah
dikonfigurasi, dan sesuai instruksi kerja sesi ini, perubahan yang melemahkan keamanan tidak
boleh diambil sebagai jalan pintas. Bila kapasitas mentah sungguh dibutuhkan, cara yang benar
adalah menjalankan generator beban terdistribusi (banyak sumber IP, mis. lewat layanan load
testing cloud) sehingga tidak pernah menyentuh ambang per-IP sama sekali — bukan melemahkan
pagarnya.
