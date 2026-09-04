import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient } from '../api/client';
import { SurveyListResponse, SurveyRow } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { EmptyState } from '../components/EmptyState';
import { ScreenHeader } from '../components/ScreenHeader';
import { MainStackParamList } from '../navigation/types';
import { colors, spacing, type } from '../theme';

const TYPE_LABELS: Record<string, string> = {
  enps: 'eNPS',
  pulse: 'Pulse Survey',
  kustom: 'Survei',
};

// Survei yang menyasar pegawai ini (bank_wide ATAU scope kantornya) DAN
// sedang aktif — pola SAMA SurveyController::index() web, lihat
// SurveyApiController.
export function SurveiScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<MainStackParamList>>();
  const insets = useSafeAreaInsets();
  const [surveys, setSurveys] = useState<SurveyRow[]>([]);
  const [respondedIds, setRespondedIds] = useState<string[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<SurveyListResponse>('/survei')
      .then((response) => {
        setSurveys(response.data.data);
        setRespondedIds(response.data.responded_ids);
      })
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Survei Keterlibatan" subtitle="Suara Anda membantu perbaikan tempat kerja" />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={surveys}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + spacing.xl }]}
        onRefresh={async () => {
          setRefreshing(true);
          await load();
          setRefreshing(false);
        }}
        refreshing={refreshing}
        renderItem={({ item }) => {
          const responded = respondedIds.includes(item.id);

          return (
            <Card style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.title} numberOfLines={2}>{item.title}</Text>
                <Badge label={TYPE_LABELS[item.type] ?? item.type} tone="neutral" />
              </View>
              {item.description ? <Text style={styles.description}>{item.description}</Text> : null}
              <Text style={styles.deadline}>Berakhir {item.end_date}</Text>
              {responded ? (
                <Badge label="Sudah Diisi" tone="success" />
              ) : (
                <TouchableOpacity style={styles.fillButton} onPress={() => navigation.navigate('SurveiIsi', { id: item.id })}>
                  <Text style={styles.fillButtonText}>Isi Survei</Text>
                  <Ionicons name="arrow-forward" size={14} color="#fff" />
                </TouchableOpacity>
              )}
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="clipboard-outline" message="Tidak ada survei aktif untuk Anda saat ini." />}
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
  card: { gap: spacing.sm },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: spacing.sm },
  title: { ...type.body, fontWeight: '700', flex: 1 },
  description: { fontSize: 12.5, color: colors.textMuted },
  deadline: { ...type.tiny },
  fillButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.xs,
    backgroundColor: colors.primary,
    borderRadius: 8,
    paddingVertical: 10,
    alignSelf: 'flex-start',
    paddingHorizontal: spacing.lg,
  },
  fillButtonText: { color: '#fff', fontWeight: '700', fontSize: 12.5 },
});
