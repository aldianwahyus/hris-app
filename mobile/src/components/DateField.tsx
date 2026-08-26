import DateTimePicker, { DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { Ionicons } from '@expo/vector-icons';
import { useState } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { formatDateLong } from '../dateUtils';
import { colors, radius, spacing, type } from '../theme';

interface Props {
  label: string;
  value: Date | null;
  onChange: (date: Date) => void;
  minimumDate?: Date;
  maximumDate?: Date;
  disabled?: boolean;
}

export function DateField({ label, value, onChange, minimumDate, maximumDate, disabled }: Props) {
  const [show, setShow] = useState(false);

  function handleChange(event: DateTimePickerEvent, selected?: Date) {
    setShow(false);

    if (event.type !== 'dismissed' && selected) {
      onChange(selected);
    }
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity
        style={[styles.field, disabled && styles.fieldDisabled]}
        onPress={() => !disabled && setShow(true)}
        activeOpacity={disabled ? 1 : 0.7}
      >
        <Ionicons name="calendar-outline" size={18} color={colors.textMuted} />
        <Text style={[styles.value, !value && styles.placeholder]}>
          {value ? formatDateLong(value) : 'Pilih tanggal'}
        </Text>
      </TouchableOpacity>
      {show && !disabled && (
        <DateTimePicker
          value={value ?? new Date()}
          mode="date"
          minimumDate={minimumDate}
          maximumDate={maximumDate}
          onChange={handleChange}
        />
      )}
    </View>
  );
}

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
    paddingVertical: 13,
    backgroundColor: colors.white,
  },
  fieldDisabled: { backgroundColor: colors.background },
  value: { ...type.body },
  placeholder: { color: colors.textMuted, fontWeight: '400' },
});
