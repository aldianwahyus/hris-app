{{--
  Tanda Tangan Elektronik (internal) — panel dipakai ulang lintas jenis
  dokumen. Butuh var: $signAction (URL tujuan submit), $contextLabel
  (nama dokumen untuk teks info, mis. "SK SK/2026/09/0001").
  Canvas HTML5 polos, TANPA pustaka eksternal — gambar ATAU nama ketik,
  salah satu wajib diisi (divalidasi JS di sini DAN server/SignDocument).
--}}
<div class="kartu" style="max-width:520px">
  <div class="kartu-judul">Tanda Tangan Elektronik</div>
  <div class="info" style="margin-bottom:14px;padding:11px 13px;background:var(--emas-muda);
    border:1px solid #E8D9A0;border-radius:8px;font-size:11.5px;color:#6B540A;line-height:1.6">
    Anda akan menandatangani {{ $contextLabel }} secara elektronik (tanda tangan internal
    organisasi — BUKAN e-materai berkekuatan hukum UU ITE).
  </div>

  <form method="POST" action="{{ $signAction }}">
    @csrf
    <input type="hidden" name="signature_image_base64" class="ttd-gambar-input">

    <label style="display:block;font-size:11.5px;font-weight:700;color:var(--teks-lemah);margin-bottom:7px">Gambar tanda tangan</label>
    <canvas class="ttd-canvas" width="440" height="150"
      style="border:1.5px solid var(--garis);border-radius:8px;background:#fff;touch-action:none;width:100%;max-width:440px;cursor:crosshair"></canvas>
    <div style="margin-top:8px">
      <button type="button" class="btn luar ttd-hapus" style="padding:6px 12px;font-size:11.5px">Hapus Gambar</button>
    </div>

    <div class="bidang" style="margin-top:14px">
      <label>Atau ketik nama sebagai tanda tangan</label>
      <input type="text" name="typed_name" class="ttd-nama-input" placeholder="Nama lengkap" maxlength="150">
    </div>

    <button type="submit" class="btn" style="margin-top:14px">Tandatangani</button>
  </form>
</div>

@once
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.ttd-canvas').forEach(function (canvas) {
        const ctx = canvas.getContext('2d');
        const form = canvas.closest('form');
        const gambarInput = form.querySelector('.ttd-gambar-input');
        const namaInput = form.querySelector('.ttd-nama-input');
        const hapusBtn = form.querySelector('.ttd-hapus');
        let drawing = false;
        let hasDrawn = false;

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0F1F1A';

        function posFromEvent(e) {
          const rect = canvas.getBoundingClientRect();
          const point = e.touches ? e.touches[0] : e;
          const scaleX = canvas.width / rect.width;
          const scaleY = canvas.height / rect.height;
          return { x: (point.clientX - rect.left) * scaleX, y: (point.clientY - rect.top) * scaleY };
        }

        function start(e) { drawing = true; hasDrawn = true; const p = posFromEvent(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
        function move(e) { if (!drawing) return; const p = posFromEvent(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
        function end() { drawing = false; }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);

        hapusBtn.addEventListener('click', function () {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          hasDrawn = false;
        });

        form.addEventListener('submit', function (e) {
          const namaKetik = namaInput.value.trim();

          if (!hasDrawn && namaKetik === '') {
            e.preventDefault();
            if (window.Swal) {
              window.Swal.fire({ icon: 'warning', title: 'Tanda tangan wajib diisi', text: 'Gambar tanda tangan atau ketik nama Anda.' });
            }
            return;
          }

          if (hasDrawn) {
            gambarInput.value = canvas.toDataURL('image/png');
          }
        });
      });
    });
  </script>
@endonce
