import { Ionicons } from '@expo/vector-icons';
import type { CSSProperties } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { formatDateLong, toIsoDate } from '../dateUtils';
import { colors, radius, spacing, type } from '../theme';

interface Props {
  label: string;
  value: Date | null;
  onChange: (date: Date) => void;
  minimumDate?: Date;
  maximumDate?: Date;
  disabled?: boolean;
}

/**
 * Varian KHUSUS WEB — @react-native-community/datetimepicker TIDAK
 * punya implementasi web sama sekali (node_modules/.../datetimepicker.js
 * merender null + console.warn "not supported on: web"), jadi versi
 * native (DateField.tsx) diam-diam tidak menampilkan apa pun di
 * preview browser. Metro otomatis memilih berkas .web.tsx ini untuk
 * build web, dan DateField.tsx (native) tetap dipakai untuk iOS/Android
 * — TIDAK ADA perubahan pada pemanggil (import { DateField } from
 * '../components/DateField' tetap sama di semua layar).
 */
export function DateField({ label, value, onChange, minimumDate, maximumDate, disabled }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <View style={[styles.field, disabled && styles.fieldDisabled]}>
        <Ionicons name="calendar-outline" size={18} color={colors.textMuted} />
        <input
          type="date"
          value={value ? toIsoDate(value) : ''}
          min={minimumDate ? toIsoDate(minimumDate) : undefined}
          max={maximumDate ? toIsoDate(maximumDate) : undefined}
          disabled={disabled}
          onChange={(event) => {
            const raw = event.target.value;

            if (raw) {
              const [year, month, day] = raw.split('-').map(Number);
              onChange(new Date(year, month - 1, day));
            }
          }}
          style={webInputStyle}
        />
      </View>
      {value && <Text style={styles.preview}>{formatDateLong(value)}</Text>}
    </View>
  );
}

const webInputStyle: CSSProperties = {
  border: 'none',
  outline: 'none',
  background: 'transparent',
  fontSize: 14,
  fontFamily: 'inherit',
  color: colors.text,
  flex: 1,
  padding: 0,
};

const styles = StyleSheet.create({
  wrap: { marginBottom: spacing.md },
  label: { ...type.caption, marginBottom: spacing.xs },
  field: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    backgroundColor: colors.white,
  },
  fieldDisabled: { backgroundColor: colors.background },
  preview: { ...type.tiny, marginTop: 4 },
});
