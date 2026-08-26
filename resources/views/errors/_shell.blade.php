<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }} — Bank NTB Syariah HCIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@vite(['resources/js/app.js'])
<style>
:root{
  --hijau:#0A7A5C; --hijau-tua:#064E3B;
  --putih:#fff; --latar:#F5F7F6; --garis:#E2E8E5;
  --teks:#0F1F1A; --teks-lemah:#5C706A;
  --merah:#B42318; --merah-muda:#FEF3F2;
  --r:10px;
}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--latar);color:var(--teks);
  -webkit-font-smoothing:antialiased;line-height:1.5;padding:24px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:var(--r);
  padding:36px 32px;max-width:440px;width:100%;text-align:center}
.ikon{font-size:40px;margin-bottom:12px}
h1{font-size:18px;margin:0 0 8px}
p{font-size:14px;color:var(--teks-lemah);margin:0 0 6px}
.ref{display:inline-block;margin-top:10px;font-family:'JetBrains Mono',monospace;font-size:12px;
  background:var(--merah-muda);color:var(--merah);border:1px solid #F3C6C2;border-radius:6px;padding:4px 10px}
.btn{display:inline-block;margin-top:20px;padding:10px 18px;border-radius:8px;font-size:13.5px;
  font-weight:600;text-decoration:none;background:var(--hijau);color:#fff}
.btn:hover{background:var(--hijau-tua)}
</style>
</head>
<body>
  <div class="kartu">
    <div class="ikon">{{ $icon }}</div>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    @isset($reference)
      <div class="ref">Kode referensi: {{ $reference }}</div>
    @endisset
    <div><a class="btn" href="{{ $linkUrl ?? url('/beranda') }}">{{ $linkText ?? 'Kembali ke Beranda' }}</a></div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', () => window.Swal?.fire({
      icon: @json($swalIcon ?? 'error'),
      title: @json($title),
      text: @json($message),
      confirmButtonText: 'Mengerti',
      confirmButtonColor: '#0A7A5C',
    }));
  </script>
</body>
</html>
