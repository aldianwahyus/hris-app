import { StyleSheet, Text, View } from 'react-native';
import { colors, radius } from '../theme';

export type BadgeTone = 'success' | 'warning' | 'danger' | 'neutral' | 'gold';

const TONE_STYLES: Record<BadgeTone, { bg: string; fg: string }> = {
  success: { bg: colors.primaryLight, fg: colors.primaryDark },
  warning: { bg: colors.warningLight, fg: colors.warning },
  danger: { bg: colors.dangerLight, fg: colors.danger },
  gold: { bg: colors.goldLight, fg: '#6B540A' },
  neutral: { bg: colors.background, fg: colors.textMuted },
};

export function Badge({ label, tone = 'neutral' }: { label: string; tone?: BadgeTone }) {
  const t = TONE_STYLES[tone];

  return (
    <View style={[styles.badge, { backgroundColor: t.bg }]}>
      <Text style={[styles.text, { color: t.fg }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: radius.pill,
    alignSelf: 'flex-start',
  },
  text: { fontSize: 11, fontWeight: '700' },
});
