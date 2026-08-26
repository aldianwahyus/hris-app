/**
 * Konfigurasi Tailwind — memetakan token desain ke kelas utilitas.
 *
 * Warna merujuk variabel CSS pada resources/css/tokens.css, bukan menyalin
 * nilai heksadesimal. Dengan begitu mode gelap bekerja otomatis tanpa
 * menulis varian dark: pada setiap komponen.
 */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.ts',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      colors: {
        hijau: {
          DEFAULT: 'var(--hris-hijau)',
          tua: 'var(--hris-hijau-tua)',
          muda: 'var(--hris-hijau-muda)',
        },
        emas: {
          DEFAULT: 'var(--hris-emas)',
          muda: 'var(--hris-emas-muda)',
        },
        permukaan: {
          DEFAULT: 'var(--hris-putih)',
          latar: 'var(--hris-latar)',
          garis: 'var(--hris-garis)',
        },
        teks: {
          DEFAULT: 'var(--hris-teks)',
          lemah: 'var(--hris-teks-lemah)',
        },
        bahaya: {
          DEFAULT: 'var(--hris-bahaya)',
          muda: 'var(--hris-bahaya-muda)',
        },
      },
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
        // Dipakai untuk seluruh nominal rupiah, jam lembur, dan saldo cuti.
        angka: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      borderRadius: {
        DEFAULT: 'var(--hris-radius)',
        kecil: 'var(--hris-radius-kecil)',
        besar: 'var(--hris-radius-besar)',
      },
    },
  },
  plugins: [],
}
