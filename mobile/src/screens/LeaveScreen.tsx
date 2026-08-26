import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { LeaveRequestRow, ListResponse } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { DateField } from '../components/DateField';
import { EmptyState } from '../components/EmptyState';
import { Fab } from '../components/Fab';
import { FormSheet } from '../components/FormSheet';
import { PrimaryButton } from '../components/PrimaryButton';
import { ScreenHeader } from '../components/ScreenHeader';
import { useToast } from '../context/ToastContext';
import { toIsoDate } from '../dateUtils';
import { requestStatus } from '../statusLabels';
import { TextField } from '../components/TextField';
import { colors, radius, spacing, type } from '../theme';

// Cuti diajukan sebagai LeaveType::CutiTahunan (tetap) — jenis lain
// belum diekspos di API Fase I, lihat LeaveApiController.
export function LeaveScreen() {
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [requests, setRequests] = useState<LeaveRequestRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [startDate, setStartDate] = useState<Date | null>(null);
  const [endDate, setEndDate] = useState<Date | null>(null);
  const [reason, setReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<LeaveRequestRow>>('/cuti')
      .then((response) => setRequests(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  function handleStartDateChange(date: Date) {
    setStartDate(date);

    // Tanggal selesai yang sudah dipilih sebelumnya bisa jadi tidak
    // valid lagi (lebih awal dari tanggal mulai baru) — backend akan
    // menolaknya (after_or_equal:start_date), tapi lebih baik dicegah
    // di sini daripada pengguna bingung kenapa pengajuan ditolak.
    if (endDate && date > endDate) {
      setEndDate(null);
    }
  }

  async function handleSubmit() {
    if (!startDate || !endDate) {
      showError('Tanggal mulai dan selesai wajib diisi.');
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await apiClient.post<{ request_number: string }>('/cuti', {
        start_date: toIsoDate(startDate),
        end_date: toIsoDate(endDate),
        reason: reason || undefined,
      });
      setStartDate(null);
      setEndDate(null);
      setReason('');
      setFormVisible(false);
      load();
      showSuccess(`Pengajuan cuti ${response.data.request_number} terkirim.`);
    } catch (error) {
      showError(apiErrorMessage(error, 'Pengajuan cuti ditolak.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <ScreenHeader title="Cuti" subtitle="Ajukan dan pantau status cuti Anda" />

      <FlatList
        data={requests}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + 100 }]}
        onRefresh={async () => {
          setRefreshing(true);
          await load();
          setRefreshing(false);
        }}
        refreshing={refreshing}
        renderItem={({ item }) => {
          const st = requestStatus(item.status);

          return (
            <Card style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.number}>{item.request_number}</Text>
                <Badge label={st.label} tone={st.tone} />
              </View>
              <View style={styles.dateRow}>
                <Ionicons name="calendar-outline" size={14} color={colors.textMuted} />
                <Text style={styles.dateText}>{item.start_date} — {item.end_date}</Text>
              </View>
              {item.reason ? <Text style={styles.reason}>{item.reason}</Text> : null}
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="calendar-outline" message="Belum ada pengajuan cuti." />}
      />

      {/* extraBottomOffset: Cuti adalah layar TAB — tab bar mengambang ikut
          tampil di sini, Fab wajib digeser ke atasnya (lihat komentar di Fab.tsx). */}
      <Fab onPress={() => setFormVisible(true)} extraBottomOffset={68} />

      <FormSheet visible={formVisible} title="Ajukan Cuti" onClose={() => setFormVisible(false)}>
        <DateField label="Tanggal Mulai" value={startDate} onChange={handleStartDateChange} minimumDate={new Date()} />
        <DateField label="Tanggal Selesai" value={endDate} onChange={setEndDate} minimumDate={startDate ?? new Date()} />
        <TextField label="Alasan (opsional)" value={reason} onChangeText={setReason} placeholder="mis. Acara keluarga" />
        <PrimaryButton label="Kirim Pengajuan" onPress={handleSubmit} loading={isSubmitting} style={{ marginTop: spacing.sm }} />
      </FormSheet>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.xl, gap: spacing.md },
  card: { gap: spacing.xs },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  number: { ...type.body, fontWeight: '700' },
  dateRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, marginTop: 2 },
  dateText: { ...type.caption },
  reason: { fontSize: 12.5, color: colors.textMuted, marginTop: spacing.xs, fontStyle: 'italic' },
});
