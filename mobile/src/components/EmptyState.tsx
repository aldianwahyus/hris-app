import { Ionicons } from '@expo/vector-icons';
import { StyleSheet, Text, View } from 'react-native';
import { colors, spacing, type } from '../theme';

export function EmptyState({ icon = 'file-tray-outline', message }: { icon?: keyof typeof Ionicons.glyphMap; message: string }) {
  return (
    <View style={styles.container}>
      <View style={styles.iconWrap}>
        <Ionicons name={icon} size={28} color={colors.textMuted} />
      </View>
      <Text style={styles.message}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { alignItems: 'center', justifyContent: 'center', paddingVertical: spacing.xxl * 2 },
  iconWrap: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: colors.background,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
  },
  message: { ...type.caption, textAlign: 'center', paddingHorizontal: spacing.xxl },
});
