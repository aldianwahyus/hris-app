import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { NotificationListResponse, NotificationRow } from '../api/types';
import { Card } from '../components/Card';
import { EmptyState } from '../components/EmptyState';
import { ScreenHeader } from '../components/ScreenHeader';
import { useToast } from '../context/ToastContext';
import { colors, radius, spacing, type } from '../theme';

export function NotificationScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { showError } = useToast();
  const [notifications, setNotifications] = useState<NotificationRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<NotificationListResponse>('/notifikasi')
      .then((response) => setNotifications(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  async function markAsRead(id: string) {
    try {
      await apiClient.post(`/notifikasi/${id}/baca`);
      setNotifications((current) =>
        current.map((item) => (item.id === id ? { ...item, read_at: new Date().toISOString() } : item)),
      );
    } catch (error) {
      showError(apiErrorMessage(error, 'Gagal menandai notifikasi sebagai dibaca.'));
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Notifikasi" subtitle="Pemberitahuan dari sistem HCIS" />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={notifications}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + spacing.xl }]}
        onRefresh={async () => {
          setRefreshing(true);
          await load();
          setRefreshing(false);
        }}
        refreshing={refreshing}
        renderItem={({ item }) => (
          <TouchableOpacity onPress={() => !item.read_at && markAsRead(item.id)} disabled={!!item.read_at} activeOpacity={0.6}>
            <Card style={[styles.card, !item.read_at && styles.cardUnread]}>
              <View style={styles.row}>
                {!item.read_at && <View style={styles.dot} />}
                <View style={{ flex: 1 }}>
                  <Text style={styles.message}>{item.data.message ?? item.data.request_number ?? item.type}</Text>
                  <Text style={styles.date}>{new Date(item.created_at).toLocaleString('id-ID')}</Text>
                </View>
              </View>
            </Card>
          </TouchableOpacity>
        )}
        ListEmptyComponent={<EmptyState icon="notifications-outline" message="Tidak ada notifikasi." />}
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
  card: {},
  cardUnread: { backgroundColor: colors.primaryLight },
  row: { flexDirection: 'row', gap: spacing.sm, alignItems: 'flex-start' },
  dot: { width: 7, height: 7, borderRadius: radius.pill, backgroundColor: colors.primary, marginTop: 5 },
  message: { ...type.body },
  date: { ...type.tiny, marginTop: 4 },
});
