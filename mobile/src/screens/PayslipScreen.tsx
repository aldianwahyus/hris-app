import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient } from '../api/client';
import { ListResponse, PayslipRow } from '../api/types';
import { Card } from '../components/Card';
import { EmptyState } from '../components/EmptyState';
import { ScreenHeader } from '../components/ScreenHeader';
import { colors, fonts, radius, spacing, type } from '../theme';

function formatRupiah(cents: number): string {
  return `Rp${(cents / 100).toLocaleString('id-ID')}`;
}

function parsePendingComponents(raw: string): string[] {
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

// Daftar saja — TIDAK ada unduh PDF di mobile (sengaja dikecualikan
// dari cakupan API Fase I, unduh tetap lewat web/sesi).
export function PayslipScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const [slips, setSlips] = useState<PayslipRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<PayslipRow>>('/slip-gaji')
      .then((response) => setSlips(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Slip Gaji" subtitle="Riwayat penghasilan bulanan" />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={slips}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + spacing.xl }]}
        onRefresh={async () => {
          setRefreshing(true);
          await load();
          setRefreshing(false);
        }}
        refreshing={refreshing}
        renderItem={({ item }) => {
          const pending = parsePendingComponents(item.pending_components);

          return (
            <Card style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.periodIcon}>
                  <Ionicons name="wallet-outline" size={18} color={colors.primary} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.period}>{item.period}</Text>
                  <Text style={styles.amount}>{formatRupiah(item.take_home_cents)}</Text>
                </View>
              </View>
              {(item.deductions.length > 0 || item.additions.length > 0) && (
                <View style={styles.lineItems}>
                  {item.additions.map((a, i) => (
                    <Text key={`a-${i}`} style={styles.lineItemAddition}>+ {formatRupiah(a.amount_cents)}{a.note ? ` (${a.note})` : ''}</Text>
                  ))}
                  {item.deductions.map((d, i) => (
                    <Text key={`d-${i}`} style={styles.lineItemDeduction}>- {formatRupiah(d.amount_cents)}{d.note ? ` (${d.note})` : ''}</Text>
                  ))}
                </View>
              )}
              {pending.length > 0 && (
                <Text style={styles.hint}>Belum termasuk: {pending.join(', ')} — unduh slip lengkap lewat aplikasi web.</Text>
              )}
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="wallet-outline" message="Belum ada slip gaji yang disetujui." />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  headerWrap: { position: 'relative' },
  back: {
    position: 'absolute',
    left: spacing.lg,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(255,255,255,0.16)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  list: { padding: spacing.xl, gap: spacing.md },
  card: { gap: spacing.xs },
  cardHeader: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  periodIcon: {
    width: 38,
    height: 38,
    borderRadius: radius.md,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  period: { ...type.h2, fontSize: 14, textTransform: 'capitalize' },
  amount: { ...type.mono, fontSize: 17, fontFamily: fonts.monoBold, color: colors.primaryDark, marginTop: 2 },
  lineItems: { marginTop: spacing.xs, gap: 2 },
  lineItemAddition: { ...type.mono, fontSize: 11.5, color: colors.primary },
  lineItemDeduction: { ...type.mono, fontSize: 11.5, color: colors.danger },
  hint: { fontSize: 11, color: colors.textMuted, marginTop: spacing.xs, lineHeight: 15 },
});
