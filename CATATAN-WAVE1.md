# Wave 1 — Antarmuka Berjalan

## Yang sudah bisa dilihat

| Alamat | Layar |
|---|---|
| `http://localhost:8080/beranda` | Beranda pegawai — saldo cuti tiga kantong, pengajuan cuti & lembur |
| `http://localhost:8080/persetujuan/lembur` | Antrean persetujuan lembur — **tombolnya berfungsi** |
| `http://localhost:8080/prototipe.html` | Prototipe statis (referensi desain) |

## Cara menjalankan

```bash
docker compose exec app php artisan migrate
```

Lalu buka `http://localhost:8080`.

Migrasi membuat 7 tabel inti dan mengisi data contoh: 6 kantor, 11 jabatan,
7 pegawai, saldo cuti, serta 6 pengajuan lembur dengan tenggat bervariasi.

## Yang berfungsi sungguhan

Tombol **Setujui** dan **Tolak** pada antrean lembur benar-benar mengubah
data. Setelah menyetujui, baris hilang dari antrean dan ringkasan di atas
ikut menyesuaikan. Ini bukan tampilan statis.

Penanda batas waktu dihitung dari selisih tanggal hari ini terhadap
`approval_deadline` — merah bila ≤ 3 hari, kuning ≤ 14 hari, hijau di atas itu.

## ⚠️ Data contoh

Daftar kantor dan pegawai **sebenarnya belum diterima** dari Divisi Human
Capital. Data pada `2026_01_01_000007_seed_sample_data.php` hanya agar
antarmuka dapat dinilai lebih awal. Nama pegawai generik, tidak merujuk
orang sungguhan.

Mengganti dengan data asli: ubah isi berkas seed tersebut, lalu

```bash
docker compose exec app php artisan migrate:refresh --step=2
```

## Yang belum ada

| Bagian | Alasan |
|---|---|
| Autentikasi & RBAC | Dipasang bersama modul Employee sebenarnya |
| Pengajuan cuti/lembur dari layar | Perlu validasi kuota & integrasi Workflow Engine |
| Absensi GPS | Menunggu koordinat kantor + kajian UU PDP |
| Payroll | Menunggu Lampiran I/II/III (tabel skala imbalan kerja) |
| Vue SPA | Tahap 9. Layar saat ini server-rendered agar berjalan tanpa build npm |

Layar saat ini memakai Blade, bukan Vue, supaya dapat langsung dijalankan
tanpa `npm install` dan `npm run build`. Token desain sudah disiapkan pada
`resources/css/tokens.css` dan `tailwind.config.js` sehingga pemindahan ke
Vue nanti memakai warna dan tipografi yang sama persis.
