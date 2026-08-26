import { Ionicons } from '@expo/vector-icons';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import {
  JetBrainsMono_500Medium,
  JetBrainsMono_700Bold,
} from '@expo-google-fonts/jetbrains-mono';
import {
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
  PlusJakartaSans_700Bold,
  PlusJakartaSans_800ExtraBold,
  useFonts,
} from '@expo-google-fonts/plus-jakarta-sans';
import { LinearGradient } from 'expo-linear-gradient';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Image, Platform, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { AuthStackParamList, MainStackParamList, MainTabParamList } from './types';
import { colors, radius, shadow } from '../theme';
import { LoginScreen } from '../screens/LoginScreen';
import { HomeScreen } from '../screens/HomeScreen';
import { AttendanceScreen } from '../screens/AttendanceScreen';
import { LeaveScreen } from '../screens/LeaveScreen';
import { OvertimeScreen } from '../screens/OvertimeScreen';
import { SppdScreen } from '../screens/SppdScreen';
import { PayslipScreen } from '../screens/PayslipScreen';
import { NotificationScreen } from '../screens/NotificationScreen';
import { IzinScreen } from '../screens/IzinScreen';
import { LainnyaScreen } from '../screens/LainnyaScreen';

const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const MainTab = createBottomTabNavigator<MainTabParamList>();
const MainStack = createNativeStackNavigator<MainStackParamList>();

const TAB_ICONS: Record<keyof MainTabParamList, { active: keyof typeof Ionicons.glyphMap; inactive: keyof typeof Ionicons.glyphMap }> = {
  Home: { active: 'home', inactive: 'home-outline' },
  Absensi: { active: 'finger-print', inactive: 'finger-print-outline' },
  Cuti: { active: 'calendar', inactive: 'calendar-outline' },
  Lembur: { active: 'time', inactive: 'time-outline' },
  Lainnya: { active: 'grid', inactive: 'grid-outline' },
};

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
    </AuthStack.Navigator>
  );
}

/** Tab bar mengambang bergaya aplikasi perbankan modern (rounded, shadow, terangkat dari tepi). */
function MainTabs() {
  const insets = useSafeAreaInsets();

  return (
    <MainTab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarShowLabel: true,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarLabelStyle: { fontSize: 10.5, fontWeight: '700' },
        tabBarStyle: {
          position: 'absolute',
          left: 16,
          right: 16,
          bottom: insets.bottom + 12,
          height: 64,
          borderRadius: radius.xl,
          borderTopWidth: 0,
          backgroundColor: colors.white,
          paddingTop: 10,
          ...shadow.floating,
        },
        tabBarIcon: ({ focused, color, size }) => {
          const icon = TAB_ICONS[route.name as keyof MainTabParamList];

          return <Ionicons name={focused ? icon.active : icon.inactive} size={size ?? 22} color={color} />;
        },
      })}
    >
      <MainTab.Screen name="Home" component={HomeScreen} options={{ title: 'Beranda' }} />
      <MainTab.Screen name="Absensi" component={AttendanceScreen} />
      <MainTab.Screen name="Cuti" component={LeaveScreen} />
      <MainTab.Screen name="Lembur" component={OvertimeScreen} />
      <MainTab.Screen name="Lainnya" component={LainnyaScreen} />
    </MainTab.Navigator>
  );
}

function MainNavigator() {
  return (
    <MainStack.Navigator screenOptions={{ headerShown: false }}>
      <MainStack.Screen name="Tabs" component={MainTabs} />
      <MainStack.Screen name="Sppd" component={SppdScreen} options={{ animation: 'slide_from_right' }} />
      <MainStack.Screen name="SlipGaji" component={PayslipScreen} options={{ animation: 'slide_from_right' }} />
      <MainStack.Screen name="Notifikasi" component={NotificationScreen} options={{ animation: 'slide_from_right' }} />
      <MainStack.Screen name="Izin" component={IzinScreen} options={{ animation: 'slide_from_right' }} />
    </MainStack.Navigator>
  );
}

/**
 * Splash screen JS (logo Bank NTB Syariah) — tampil selama AuthContext
 * memeriksa sesi tersimpan (isLoading). Terpisah dari splash NATIVE
 * (dikonfigurasi lewat plugin expo-splash-screen di app.json, ditahan
 * oleh SplashScreen.preventAutoHideAsync() di App.tsx): splash native
 * hanya efektif penuh di build native/Expo Go (bukan preview web), jadi
 * layar JS ini yang menjamin logo tetap tampil di SEMUA platform selama
 * jeda pengecekan sesi — begitu isLoading selesai, splash native
 * ditutup (hideAsync) TEPAT saat layar ini juga lepas, tanpa jeda layar
 * kosong di antaranya.
 */
function SplashView() {
  return (
    <LinearGradient colors={[colors.primaryDark, colors.primary]} style={splashStyles.container}>
      {/* Kartu putih di belakang logo — teks "Bank NTB Syariah" pada logo
          berwarna hijau tua, nyaris tak terbaca kalau langsung di atas
          gradient hijau (dibuktikan lewat tinjauan visual ikon app.json
          sebelum perbaikan ini), pola SAMA ".plat" pada halaman login web. */}
      <View style={splashStyles.kartu}>
        <Image source={require('../../assets/splash-logo.png')} style={splashStyles.logo} resizeMode="contain" />
      </View>
      <ActivityIndicator size="small" color="#fff" style={splashStyles.spinner} />
    </LinearGradient>
  );
}

const splashStyles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  kartu: { backgroundColor: '#fff', borderRadius: 20, paddingVertical: 22, paddingHorizontal: 28 },
  logo: { width: 200, height: 65 },
  spinner: { marginTop: 28 },
});

// Splash minimal tampil MINIMAL_SPLASH_MS (diminta pengguna: 5-7 detik)
// supaya benar-benar terlihat — di web khususnya, pengecekan sesi
// tersimpan (readSession) nyaris instan (bukan I/O native sungguhan),
// jadi tanpa penundaan ini splash bisa lepas dalam hitungan milidetik
// dan terasa "tidak pernah muncul" walau sebenarnya sempat dirender.
const MINIMAL_SPLASH_MS = 6000;

export function RootNavigator() {
  const { user, isLoading } = useAuth();
  const [minimalDurationDone, setMinimalDurationDone] = useState(false);
  const [fontsLoaded] = useFonts({
    PlusJakartaSans_400Regular,
    PlusJakartaSans_500Medium,
    PlusJakartaSans_600SemiBold,
    PlusJakartaSans_700Bold,
    PlusJakartaSans_800ExtraBold,
    JetBrainsMono_500Medium,
    JetBrainsMono_700Bold,
  });

  useEffect(() => {
    const timer = setTimeout(() => setMinimalDurationDone(true), MINIMAL_SPLASH_MS);

    return () => clearTimeout(timer);
  }, []);

  const showSplash = isLoading || !minimalDurationDone || !fontsLoaded;

  useEffect(() => {
    // Splash NATIVE (expo-splash-screen) tidak berlaku di web sama sekali
    // (lihat komentar SplashView) — preventAutoHideAsync()/hideAsync()
    // dijaga hanya untuk platform native supaya tidak ada panggilan
    // API yang tak didukung di web.
    if (!showSplash && Platform.OS !== 'web') {
      SplashScreen.hideAsync().catch(() => {});
    }
  }, [showSplash]);

  if (showSplash) {
    return <SplashView />;
  }

  return <NavigationContainer>{user ? <MainNavigator /> : <AuthNavigator />}</NavigationContainer>;
}
