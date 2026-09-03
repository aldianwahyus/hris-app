<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('judul', 'Karier') — Bank NTB Syariah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --hijau:#0A7A5C; --hijau-tua:#064E3B; --hijau-muda:#E6F2ED;
  --emas:#C9A227; --emas-muda:#FBF4DE;
  --putih:#fff; --latar:#F5F7F6; --garis:#E2E8E5;
  --teks:#0F1F1A; --teks-lemah:#5C706A;
  --merah:#B42318; --merah-muda:#FEF3F2;
  --r:10px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--latar);
  color:var(--teks);-webkit-font-smoothing:antialiased;line-height:1.5}
a{color:inherit;text-decoration:none}
:focus-visible{outline:2px solid var(--emas);outline-offset:2px}

header.publik{background:var(--hijau-tua);color:#fff;padding:16px 20px}
header.publik .merek{max-width:760px;margin:0 auto;display:flex;align-items:center;gap:10px;font-weight:800;font-size:15px}

.wadah{max-width:760px;margin:0 auto;padding:28px 18px 60px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);padding:20px;margin-bottom:14px}
.kartu-judul{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--teks-lemah);margin-bottom:13px}
.pesan{padding:11px 14px;border-radius:9px;font-size:12.5px;font-weight:600;margin-bottom:15px}
.pesan.sukses{background:var(--hijau-muda);color:var(--hijau-tua);border:1px solid #BFDED2}
.pesan.gagal{background:var(--merah-muda);color:var(--merah);border:1px solid #F3C6C2}

.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;
  font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;border:1px solid transparent;
  background:var(--hijau);color:#fff;transition:.15s}
.btn:hover{background:var(--hijau-tua)}
.btn.luar{background:var(--putih);color:var(--teks);border-color:var(--garis)}
.btn.luar:hover{background:var(--latar)}

.bidang{margin-bottom:16px}
.bidang label{display:block;font-size:12px;font-weight:600;color:var(--teks-lemah);margin-bottom:6px}
.bidang input,.bidang select,.bidang textarea{width:100%;padding:10px 12px;border:1px solid var(--garis);
  border-radius:8px;font-size:13.5px;font-family:inherit;color:var(--teks);background:var(--putih)}
.bidang input:focus,.bidang select:focus,.bidang textarea:focus{border-color:var(--hijau)}

footer.publik{text-align:center;padding:20px;font-size:11px;color:var(--teks-lemah)}
@yield('gaya')
</style>
</head>
<body>
<header class="publik">
  <div class="merek">🏦 Karier Bank NTB Syariah</div>
</header>
<div class="wadah">
  @if (session('sukses'))
    <div class="pesan sukses">{{ session('sukses') }}</div>
  @endif
  @if (session('gagal'))
    <div class="pesan gagal">{{ session('gagal') }}</div>
  @endif
  @yield('isi')
</div>
<footer class="publik">&copy; {{ date('Y') }} Bank NTB Syariah. Seluruh lowongan yang sah hanya ditayangkan di halaman ini.</footer>
</body>
</html>
