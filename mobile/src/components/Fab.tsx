import { Ionicons } from '@expo/vector-icons';
import { StyleSheet, TouchableOpacity } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius, shadow } from '../theme';

interface Props {
  onPress: () => void;
  /**
   * Jarak tambahan dari tepi bawah aman (di ATAS insets.bottom bawaan) —
   * WAJIB diisi lebih besar di layar yang jadi tab (Cuti/Lembur), supaya
   * Fab tidak tertimpa tab bar mengambang (lihat tabBarStyle di
   * RootNavigator.tsx: bottom = insets.bottom + 12, height = 64, jadi
   * tepi ATAS tab bar ada di insets.bottom + 76). Layar yang di-push di
   * ATAS tab bar (Sppd, dipanggil dari menu Lainnya) tidak perlu ini —
   * tab bar tidak ikut ter-render di sana sama sekali.
   */
  extraBottomOffset?: number;
}

export function Fab({ onPress, extraBottomOffset = 0 }: Props) {
  const insets = useSafeAreaInsets();

  return (
    <TouchableOpacity
      style={[styles.fab, { bottom: insets.bottom + 24 + extraBottomOffset }]}
      onPress={onPress}
      activeOpacity={0.85}
    >
      <Ionicons name="add" size={28} color="#fff" />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  fab: {
    position: 'absolute',
    right: 20,
    width: 56,
    height: 56,
    borderRadius: radius.pill,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    ...shadow.floating,
  },
});
