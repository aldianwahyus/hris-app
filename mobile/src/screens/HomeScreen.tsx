import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient } from '../api/client';
import { NotificationListResponse } from '../api/types';
import { Card } from '../components/Card';
import { useAuth } from '../context/AuthContext';
import { MobileMenuKey, useMobileMenu } from '../context/MobileMenuContext';
import { MainStackParamList, MainTabParamList, ParamlessMainStackRoute } from '../navigation/types';
import { colors, radius, spacing, type } from '../theme';

type QuickAction = {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  tab?: keyof MainTabParamList;
  stack?: ParamlessMainStackRoute;
  // undefined = selalu tampil (mis. "Lainnya", bukan menu fitur yang bisa dimatikan admin).
  menuKey?: MobileMenuKey;
};

const QUICK_ACTIONS: QuickAction[] = [
  { icon: 'finger-print', label: 'Absensi', tab: 'Absensi', menuKey: 'absensi' },
  { icon: 'calendar', label: 'Cuti', tab: 'Cuti', menuKey: 'cuti' },
  { icon: 'time', label: 'Lembur', tab: 'Lembur', menuKey: 'lembur' },
  { icon: 'airplane', label: 'SPPD', stack: 'Sppd', menuKey: 'sppd' },
  { icon: 'document-text', label: 'Izin', stack: 'Izin', menuKey: 'izin' },
  { icon: 'wallet', label: 'Slip Gaji', stack: 'SlipGaji', menuKey: 'slip_gaji' },
  { icon: 'grid', label: 'Lainnya', tab: 'Lainnya' },
];

export function HomeScreen() {
  const { user } = useAuth();
  const { isEnabled } = useMobileMenu();
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<NativeStackNavigationProp<MainTabParamList & MainStackParamList>>();
  const [unreadCount, setUnreadCount] = useState(0);
  const quickActions = QUICK_ACTIONS.filter((action) => !action.menuKey || isEnabled(action.menuKey));

  useFocusEffect(
    useCallback(() => {
      apiClient
        .get<NotificationListResponse>('/notifikasi')
        .then((response) => setUnreadCount(response.data.unread_count))
        .catch(() => {});
    }, []),
  );

  const firstName = (user?.nama ?? user?.nrp ?? '').split(' ')[0];

  return (
    <View style={styles.container}>
      <LinearGradient
        colors={[colors.primaryDark, colors.primary]}
        style={[styles.header, { paddingTop: insets.top + spacing.lg }]}
      >
        <View style={styles.headerRow}>
          <View>
            <Text style={styles.greeting}>Halo, {firstName} 👋</Text>
            <Text style={styles.greetingSub}>Semoga harimu produktif</Text>
          </View>
          {isEnabled('notifikasi') && (
            <TouchableOpacity style={styles.bell} onPress={() => navigation.navigate('Notifikasi')} activeOpacity={0.7}>
              <Ionicons name="notifications-outline" size={20} color="#fff" />
              {unreadCount > 0 && (
                <View style={styles.bellBadge}>
                  <Text style={styles.bellBadgeText}>{unreadCount > 9 ? '9+' : unreadCount}</Text>
                </View>
              )}
            </TouchableOpacity>
          )}
        </View>
      </LinearGradient>

      <ScrollView contentContainerStyle={[styles.body, { paddingBottom: insets.bottom + 100 }]}>
        <Card style={styles.quickCard}>
          <Text style={styles.sectionTitle}>Menu Cepat</Text>
          <View style={styles.grid}>
            {quickActions.map((action) => (
              <TouchableOpacity
                key={action.label}
                style={styles.gridItem}
                activeOpacity={0.7}
                onPress={() => (action.tab ? navigation.navigate(action.tab) : navigation.navigate(action.stack!))}
              >
                <View style={styles.gridIcon}>
                  <Ionicons name={action.icon} size={22} color={colors.primary} />
                </View>
                <Text style={styles.gridLabel}>{action.label}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </Card>

        <Card style={styles.tipCard}>
          <Ionicons name="bulb-outline" size={20} color={colors.gold} />
          <Text style={styles.tipText}>
            Jangan lupa absen masuk & pulang setiap hari kerja — jam lembur dihitung otomatis dari data ini.
          </Text>
        </Card>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: {
    paddingHorizontal: spacing.xl,
    paddingBottom: spacing.xxl,
    borderBottomLeftRadius: radius.xl,
    borderBottomRightRadius: radius.xl,
  },
  headerRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  greeting: { fontSize: 19, fontWeight: '800', color: '#fff' },
  greetingSub: { fontSize: 12.5, color: 'rgba(255,255,255,0.85)', marginTop: 3 },
  bell: {
    width: 40,
    height: 40,
    borderRadius: radius.pill,
    backgroundColor: 'rgba(255,255,255,0.16)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bellBadge: {
    position: 'absolute',
    top: -2,
    right: -2,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    paddingHorizontal: 3,
    backgroundColor: colors.danger,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderColor: colors.primaryDark,
  },
  bellBadgeText: { color: '#fff', fontSize: 9, fontWeight: '800' },
  body: { padding: spacing.xl, marginTop: -spacing.lg, gap: spacing.lg },
  quickCard: {},
  sectionTitle: { ...type.caption, textTransform: 'uppercase', letterSpacing: 0.6, marginBottom: spacing.md },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  gridItem: { width: '30%', alignItems: 'center', gap: spacing.xs },
  gridIcon: {
    width: 52,
    height: 52,
    borderRadius: radius.md,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  gridLabel: { fontSize: 11.5, fontWeight: '700', color: colors.text, textAlign: 'center' },
  tipCard: { flexDirection: 'row', gap: spacing.md, alignItems: 'flex-start', backgroundColor: colors.goldLight },
  tipText: { flex: 1, fontSize: 12.5, color: '#6B540A', lineHeight: 18, fontWeight: '500' },
});
