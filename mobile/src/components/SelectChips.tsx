import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { colors, radius, spacing, type } from '../theme';

interface Option {
  value: string;
  label: string;
}

interface Props {
  label?: string;
  options: Option[];
  value: string | null;
  onChange: (value: string) => void;
  clearable?: boolean;
}

export function SelectChips({ label, options, value, onChange, clearable }: Props) {
  return (
    <View style={styles.wrap}>
      {label ? <Text style={styles.label}>{label}</Text> : null}
      <View style={styles.row}>
        {options.map((option) => {
          const active = value === option.value;

          return (
            <TouchableOpacity
              key={option.value}
              style={[styles.chip, active && styles.chipActive]}
              onPress={() => onChange(clearable && active ? '' : option.value)}
              activeOpacity={0.7}
            >
              <Text style={active ? styles.chipTextActive : styles.chipText}>{option.label}</Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: spacing.md },
  label: { ...type.caption, marginBottom: spacing.xs },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.pill,
    paddingVertical: 8,
    paddingHorizontal: 14,
    backgroundColor: colors.white,
  },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { fontSize: 12.5, fontWeight: '600', color: colors.text },
  chipTextActive: { fontSize: 12.5, fontWeight: '600', color: '#fff' },
});
