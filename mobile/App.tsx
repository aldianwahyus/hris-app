import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { Platform, Text, TextInput } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from './src/context/AuthContext';
import { ToastProvider } from './src/context/ToastContext';
import { RootNavigator } from './src/navigation/RootNavigator';
import { fonts } from './src/theme';

// Tahan splash screen native (logo Bank NTB Syariah, dikonfigurasi lewat
// plugin expo-splash-screen di app.json) sampai RootNavigator memutuskan
// sesi tersimpan sudah dicek DAN font Plus Jakarta Sans/JetBrains Mono
// (sama persis aplikasi web) sudah dimuat — dipanggil SplashScreen.
// hideAsync() di sana, BUKAN di sini, supaya tidak ada jeda layar kosong
// di antara splash native lepas dan konten pertama tampil. TIDAK berlaku
// di web (modul native tidak ada di sana) — RootNavigator punya splash
// JS terpisah yang menjamin logo tetap tampil di web.
if (Platform.OS !== 'web') {
  SplashScreen.preventAutoHideAsync().catch(() => {});
}

// Bobot default (Regular) untuk SEMUA <Text>/<TextInput> yang BELUM
// memakai token type.* dari theme.ts (mis. style ad-hoc lokal per layar
// yang hanya menyetel fontWeight) — memastikan tipografi Plus Jakarta
// Sans konsisten di seluruh aplikasi, bukan cuma spot yang sempat
// disentuh manual. Token type.* (theme.ts) tetap MENIMPA ini dengan
// berkas bobot yang tepat (SemiBold/Bold/ExtraBold) lewat fontFamily
// masing-masing, tidak kena default ini.
const defaultTextStyle = { fontFamily: fonts.regular };

// @ts-expect-error -- defaultProps tidak lagi ada di tipe resmi React Native,
// tapi properti ini tetap dibaca runtime-nya (pola umum global-default-font Expo).
Text.defaultProps = Text.defaultProps || {};
// @ts-expect-error -- lihat catatan di atas.
Text.defaultProps.style = [defaultTextStyle, Text.defaultProps.style];
// @ts-expect-error -- lihat catatan di atas.
TextInput.defaultProps = TextInput.defaultProps || {};
// @ts-expect-error -- lihat catatan di atas.
TextInput.defaultProps.style = [defaultTextStyle, TextInput.defaultProps.style];

export default function App() {
  return (
    <SafeAreaProvider>
      <ToastProvider>
        <AuthProvider>
          <RootNavigator />
        </AuthProvider>
      </ToastProvider>
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}
