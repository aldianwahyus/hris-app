import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, ViewStyle } from 'react-native';
import { colors, radius, spacing } from '../theme';

interface Props {
  label: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  variant?: 'primary' | 'outline';
  style?: ViewStyle;
}

export function PrimaryButton({ label, onPress, loading, disabled, variant = 'primary', style }: Props) {
  const outline = variant === 'outline';

  return (
    <TouchableOpacity
      style={[styles.base, outline ? styles.outline : styles.solid, disabled && styles.disabled, style]}
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.85}
    >
      {loading ? (
        <ActivityIndicator color={outline ? colors.primary : '#fff'} />
      ) : (
        <Text style={outline ? styles.outlineText : styles.solidText}>{label}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  base: {
    borderRadius: radius.md,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: spacing.sm,
  },
  solid: { backgroundColor: colors.primary },
  solidText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  outline: { backgroundColor: 'transparent', borderWidth: 1.5, borderColor: colors.primary },
  outlineText: { color: colors.primary, fontSize: 15, fontWeight: '700' },
  disabled: { opacity: 0.5 },
});
