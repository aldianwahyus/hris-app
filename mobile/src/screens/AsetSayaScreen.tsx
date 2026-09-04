import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient } from '../api/client';
import { AssetAssignmentRow, ListResponse } from '../api/types';
import { Card } from '../components/Card';
import { EmptyState } from '../components/EmptyState';
import { ScreenHeader } from '../components/ScreenHeader';
import { colors, radius, spacing, type } from '../theme';

// Daftar saja — BACA SAJA, cermin AssetAssignmentController::mine() (penugasan/
// pengembalian aset TETAP murni admin web, lihat AssetAssignmentController).
export function AsetSayaScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const [assets, setAssets] = useState<AssetAssignmentRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<AssetAssignmentRow>>('/aset')
      .then((response) => setAssets(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Aset Saya" subtitle="Aset perusahaan yang sedang Anda pegang" />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={assets}
        keyExtractor={(item) => item.asset_code}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + spacing.xl }]}
        onRefresh={async () => {
          setRefreshing(true);
          await load();
          setRefreshing(false);
        }}
        refreshing={refreshing}
        renderItem={({ item }) => (
          <Card style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.icon}>
                <Ionicons name="cube-outline" size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{item.name}</Text>
                <Text style={styles.code}>{item.asset_code} · {item.category}</Text>
              </View>
            </View>
            {(item.brand_model || item.serial_number) && (
              <Text style={styles.detail}>
                {item.brand_model ?? '—'}{item.serial_number ? ` · SN ${item.serial_number}` : ''}
              </Text>
            )}
            <Text style={styles.assignedAt}>Ditugaskan sejak {item.assigned_at}</Text>
          </Card>
        )}
        ListEmptyComponent={<EmptyState icon="cube-outline" message="Tidak ada aset yang sedang Anda pegang." />}
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
  icon: {
    width: 38,
    height: 38,
    borderRadius: radius.md,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { ...type.body, fontWeight: '700' },
  code: { ...type.caption, marginTop: 1 },
  detail: { fontSize: 12.5, color: colors.text, marginTop: spacing.xs },
  assignedAt: { ...type.tiny, marginTop: 2 },
});
