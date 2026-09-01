import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Card } from '../components/Card';
import { ScreenHeader } from '../components/ScreenHeader';
import { useAuth } from '../context/AuthContext';
import { MobileMenuKey, useMobileMenu } from '../context/MobileMenuContext';
import { MainStackParamList } from '../navigation/types';
import { colors, radius, spacing, type } from '../theme';

const MENU: { icon: keyof typeof Ionicons.glyphMap; label: string; description: string; route: keyof MainStackParamList; menuKey: MobileMenuKey }[] = [
  { icon: 'airplane-outline', label: 'SPPD', description: 'Perjalanan dinas', route: 'Sppd', menuKey: 'sppd' },
  { icon: 'document-text-outline', label: 'Izin', description: 'Izin tidak masuk bekerja', route: 'Izin', menuKey: 'izin' },
  { icon: 'wallet-outline', label: 'Slip Gaji', description: 'Riwayat penghasilan', route: 'SlipGaji', menuKey: 'slip_gaji' },
  { icon: 'notifications-outline', label: 'Notifikasi', description: 'Pemberitahuan sistem', route: 'Notifikasi', menuKey: 'notifikasi' },
];

export function LainnyaScreen() {
  const { user, logout } = useAuth();
  const { isEnabled } = useMobileMenu();
  const navigation = useNavigation<NativeStackNavigationProp<MainStackParamList>>();
  const insets = useSafeAreaInsets();
  const menu = MENU.filter((item) => isEnabled(item.menuKey));

  return (
    <View style={styles.container}>
      <ScreenHeader title="Lainnya" subtitle="Fitur tambahan & profil akun" />
      <ScrollView contentContainerStyle={[styles.body, { paddingBottom: insets.bottom + 100 }]}>
        <Card style={styles.profileCard}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{(user?.nama ?? user?.nrp ?? '?').charAt(0).toUpperCase()}</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={type.h2}>{user?.nama ?? user?.nrp}</Text>
            <Text style={styles.nrp}>NRP {user?.nrp}</Text>
            <Text style={styles.roles}>{user?.roles.join(' · ')}</Text>
          </View>
        </Card>

        {menu.length > 0 && (
          <>
            <Text style={styles.sectionLabel}>Menu</Text>
            <Card style={styles.menuCard}>
              {menu.map((item, index) => (
                <TouchableOpacity
                  key={item.route}
                  style={[styles.menuRow, index < menu.length - 1 && styles.menuRowBorder]}
                  onPress={() => navigation.navigate(item.route)}
                  activeOpacity={0.6}
                >
                  <View style={styles.menuIcon}>
                    <Ionicons name={item.icon} size={19} color={colors.primary} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.menuLabel}>{item.label}</Text>
                    <Text style={styles.menuDescription}>{item.description}</Text>
                  </View>
                  <Ionicons name="chevron-forward" size={18} color={colors.textMuted} />
                </TouchableOpacity>
              ))}
            </Card>
          </>
        )}

        <TouchableOpacity style={styles.logout} onPress={logout} activeOpacity={0.7}>
          <Ionicons name="log-out-outline" size={18} color={colors.danger} />
          <Text style={styles.logoutText}>Keluar</Text>
        </TouchableOpacity>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  body: { padding: spacing.xl, gap: spacing.lg },
  profileCard: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  avatar: {
    width: 52,
    height: 52,
    borderRadius: radius.pill,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { fontSize: 20, fontWeight: '800', color: colors.primaryDark },
  nrp: { ...type.caption, marginTop: 2 },
  roles: { ...type.tiny, marginTop: 2, textTransform: 'capitalize' },
  sectionLabel: { ...type.caption, textTransform: 'uppercase', letterSpacing: 0.6, marginBottom: -spacing.sm },
  menuCard: { padding: 0, overflow: 'hidden' },
  menuRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, padding: spacing.lg },
  menuRowBorder: { borderBottomWidth: 1, borderBottomColor: colors.border },
  menuIcon: {
    width: 38,
    height: 38,
    borderRadius: radius.md,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  menuLabel: { ...type.body, fontWeight: '700' },
  menuDescription: { ...type.tiny, marginTop: 1 },
  logout: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.md,
  },
  logoutText: { color: colors.danger, fontWeight: '700', fontSize: 14 },
});
