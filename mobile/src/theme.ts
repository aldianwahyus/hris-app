/**
 * Palet warna SAMA persis dengan aplikasi web (resources/views/layouts/app.blade.php
 * :root) — hijau tua khas Bank NTB Syariah + aksen emas — supaya identitas
 * visual konsisten lintas web dan mobile, bukan dua bahasa desain berbeda.
 */
export const colors = {
  primary: '#0A7A5C',
  primaryDark: '#064E3B',
  primaryLight: '#E6F2ED',
  gold: '#C9A227',
  goldLight: '#FBF4DE',
  white: '#FFFFFF',
  background: '#F5F7F6',
  border: '#E2E8E5',
  text: '#0F1F1A',
  textMuted: '#5C706A',
  danger: '#B42318',
  dangerLight: '#FEF3F2',
  warning: '#92400E',
  warningLight: '#FEF3C7',
};

export const spacing = { xs: 4, sm: 8, md: 12, lg: 16, xl: 20, xxl: 28 };

export const radius = { sm: 10, md: 14, lg: 20, xl: 28, pill: 999 };

export const shadow = {
  card: {
    shadowColor: '#0F1F1A',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 2,
  },
  floating: {
    shadowColor: '#0F1F1A',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.14,
    shadowRadius: 20,
    elevation: 8,
  },
};

/**
 * Plus Jakarta Sans (teks) + JetBrains Mono (angka) — SAMA PERSIS dengan
 * aplikasi web (resources/views/layouts/app.blade.php: body{font-family:
 * 'Plus Jakarta Sans'...}, .angka{font-family:'JetBrains Mono'...}).
 * fontFamily di sini SUDAH mengandung bobotnya sendiri (berkas Google
 * Font terpisah per bobot) — sengaja TIDAK dipasangkan lagi dengan
 * fontWeight, supaya OS tidak mem-fake-bold berkas yang sudah tepat
 * bobotnya (lihat App.tsx untuk fallback default Text di luar token ini).
 */
export const fonts = {
  regular: 'PlusJakartaSans_400Regular',
  medium: 'PlusJakartaSans_500Medium',
  semiBold: 'PlusJakartaSans_600SemiBold',
  bold: 'PlusJakartaSans_700Bold',
  extraBold: 'PlusJakartaSans_800ExtraBold',
  mono: 'JetBrainsMono_500Medium',
  monoBold: 'JetBrainsMono_700Bold',
};

export const type = {
  h1: { fontSize: 22, fontFamily: fonts.extraBold, color: colors.text },
  h2: { fontSize: 17, fontFamily: fonts.bold, color: colors.text },
  body: { fontSize: 14, fontFamily: fonts.medium, color: colors.text },
  caption: { fontSize: 12, fontFamily: fonts.semiBold, color: colors.textMuted },
  tiny: { fontSize: 11, fontFamily: fonts.semiBold, color: colors.textMuted },
  /** Angka/nominal/jam — pola SAMA kelas .angka pada aplikasi web. */
  mono: { fontSize: 13, fontFamily: fonts.mono, color: colors.text },
};
