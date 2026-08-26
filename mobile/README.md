# HCIS Mobile (ESS)

Aplikasi Employee Self-Service (React Native/Expo) untuk pegawai Bank NTB Syariah —
klien tipis dari API `/api/v1/*` yang sudah ada di backend HRIS (Laravel Sanctum, lihat
`routes/api.php`). Cakupan: login, absensi GPS, cuti, lembur, SPPD, slip gaji, notifikasi.

## Menjalankan (lewat Docker, tidak perlu install Node di host)

Repo ini menambahkan service `node` khusus di `docker-compose.yml` (root proyek) untuk
tooling Expo/npm, mengikuti pola `docker compose exec app php artisan ...` yang sudah biasa
dipakai untuk backend.

```bash
# Dari root repo (d:/projects/hris-app), bukan dari dalam folder mobile/
docker compose up -d node
docker compose exec node npm install       # sekali saja / setelah package.json berubah
cp mobile/.env.example mobile/.env         # lalu isi EXPO_PUBLIC_API_URL dengan IP LAN Anda
docker compose exec node npx expo start
```

Scan QR code yang muncul dengan aplikasi **Expo Go** di HP (App Store/Play Store) — HP harus
satu jaringan Wi-Fi/LAN dengan mesin dev. Kalau tidak satu jaringan (mis. HP pakai data
seluler, atau jaringan kantor memblokir), tambahkan `--tunnel`:

```bash
docker compose exec node npx expo start --tunnel
```

## Menjalankan di Emulator/Simulator

Emulator butuh GUI/GPU, jadi harus jalan di **mesin host** (bukan di dalam container Docker) —
tapi Metro bundler-nya tetap dari container `node` yang sudah ada. Windows hanya bisa emulator
Android (iOS Simulator perlu macOS).

1. Install **Android Studio**, buat AVD lewat Device Manager, jalankan emulatornya.
2. Isi `mobile/.env` dengan alias khusus emulator Android (BUKAN `localhost`/IP LAN):
   ```
   EXPO_PUBLIC_API_URL=http://10.0.2.2:8080/api/v1
   ```
   `10.0.2.2` dari DALAM emulator Android selalu menunjuk ke `localhost` mesin host.
3. Pastikan Metro jalan: `docker compose exec node npx expo start`.
4. Di dalam emulator: install **Expo Go** dari Play Store, buka, pilih "Enter URL manually",
   masukkan `exp://10.0.2.2:8081`.

Alternatif lebih praktis kalau Node.js ter-install di host (di luar Docker): jalankan
`npx expo start --android` langsung dari folder `mobile/` di host — Expo CLI bisa otomatis
mendeteksi & membuka emulator lewat `adb` (tekan `a` di terminal), tanpa isi URL manual. Ini
TIDAK bisa dilakukan dari dalam container `node` karena container tidak punya akses ke
`adb`/emulator yang jalan di host.

## Struktur

- `src/api/client.ts` — instance axios + penyimpanan token (expo-secure-store) + penanganan 401.
- `src/api/types.ts` — bentuk JSON tiap endpoint, dicocokkan langsung dari kode controller
  backend (bukan tebakan) — lihat komentar di tiap tipe untuk sumbernya.
- `src/context/AuthContext.tsx` — status login, `login()`/`logout()`.
- `src/navigation/` — stack Login vs tab utama (React Navigation).
- `src/screens/` — 7 layar: Login, Home, Absensi, Cuti, Lembur, SPPD, Slip Gaji (+ Notifikasi).

## Catatan penting

- **Login TIDAK memakai captcha** — backend punya `ApiLoginRequest` khusus mobile yang
  melewati captcha sepenuhnya (captcha di aplikasi web bergantung sesi, klien mobile asli
  tidak pernah punya sesi). Pertahanan brute-force untuk jalur ini adalah rate-limit ganda
  yang sudah ada di server (5x percobaan per NRP+IP, 20x/900 detik per NRP saja) — bukan
  sesuatu yang perlu ditangani di klien.
- **Absensi**: server yang memvalidasi radius kantor (geofence) — klien hanya mengirim
  koordinat GPS mentah, tidak menghitung jarak sendiri.
- **Lembur**: jam dihitung otomatis dari bukti absensi pada tanggal yang dipilih — form TIDAK
  punya field jam sama sekali (memang sengaja, bukan kelalaian).
- **Slip gaji**: hanya daftar + nominal take-home *sementara* (`take_home_partial_cents`) —
  belum termasuk komponen yang tercantum di `pending_components`. Unduh PDF slip lengkap
  tetap lewat aplikasi web (butuh sesi web, di luar cakupan API mobile).
- `EXPO_PUBLIC_API_URL` **wajib** IP LAN mesin dev, bukan `localhost` — HP fisik/emulator
  adalah perangkat terpisah dari mesin dev.

## Verifikasi yang SUDAH dilakukan (otomatis)

- `npx tsc --noEmit` — seluruh kode TypeScript valid tanpa error tipe.

## Verifikasi yang BELUM bisa dilakukan otomatis — perlu dicoba manual

Tidak ada simulator/emulator/perangkat fisik di lingkungan pengembangan ini, jadi berikut
belum pernah benar-benar dijalankan secara visual dan perlu dicoba oleh tim:

1. `expo start`, scan QR dengan Expo Go, pastikan layar Login muncul tanpa error.
2. Login pakai akun uji yang sudah ada di database dev.
3. Coba "Absen Sekarang" di lokasi yang benar-benar dalam radius kantor — pastikan izin
   lokasi diminta dan absensi tercatat.
4. Coba ajukan Cuti/Lembur/SPPD masing-masing satu kali, pastikan muncul di daftar.
5. Buka Slip Gaji dan Notifikasi, pastikan data tampil dan tandai-dibaca berfungsi.
