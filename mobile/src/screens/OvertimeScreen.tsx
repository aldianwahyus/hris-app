import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { ListResponse, OvertimeRequestRow } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { DateField } from '../components/DateField';
import { EmptyState } from '../components/EmptyState';
import { Fab } from '../components/Fab';
import { FormSheet } from '../components/FormSheet';
import { PrimaryButton } from '../components/PrimaryButton';
import { ScreenHeader } from '../components/ScreenHeader';
import { SelectChips } from '../components/SelectChips';
import { useToast } from '../context/ToastContext';
import { toIsoDate } from '../dateUtils';
import { requestStatus } from '../statusLabels';
import { colors, spacing, type } from '../theme';

// Jenis lembur backend (OvertimeType): regular | crash_program | shift_picket.
const OVERTIME_TYPES = [
  { value: 'regular', label: 'Reguler' },
  { value: 'crash_program', label: 'Crash Program' },
  { value: 'shift_picket', label: 'Shift/Piket' },
];

export function OvertimeScreen() {
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [requests, setRequests] = useState<OvertimeRequestRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [workDate, setWorkDate] = useState<Date | null>(null);
  const [overtimeType, setOvertimeType] = useState('regular');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<OvertimeRequestRow>>('/lembur')
      .then((response) => setRequests(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  function confirmCancel(item: OvertimeRequestRow) {
    Alert.alert('Batalkan Pengajuan', `Batalkan pengajuan lembur ${item.spkl_number}?`, [
      { text: 'Tidak', style: 'cancel' },
      { text: 'Ya, Batalkan', style: 'destructive', onPress: () => cancelRequest(item.id) },
    ]);
  }

  async function cancelRequest(id: string) {
    try {
      await apiClient.post(`/lembur/${id}/batal`);
      showSuccess('Pengajuan lembur berhasil dibatalkan.');
      load();
    } catch (error) {
      showError(apiErrorMessage(error, 'Pengajuan tidak dapat dibatalkan.'));
    }
  }

  async function handleSubmit() {
    if (!workDate) {
      showError('Tanggal kerja wajib diisi.');
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await apiClient.post<{ spkl_number: string }>('/lembur', {
        overtime_type: overtimeType,
        work_date: toIsoDate(workDate),
      });
      setWorkDate(null);
      setFormVisible(false);
      load();
      showSuccess(`Pengajuan lembur ${response.data.spkl_number} terkirim.`);
    } catch (error) {
      showError(apiErrorMessage(error, 'Pengajuan lembur ditolak.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <ScreenHeader title="Lembur" subtitle="Jam dihitung otomatis dari bukti absensi" />

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
          const typeLabel = OVERTIME_TYPES.find((t) => t.value === item.overtime_type)?.label ?? item.overtime_type;

          return (
            <Card style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.number}>{item.spkl_number}</Text>
                <Badge label={st.label} tone={st.tone} />
              </View>
              <View style={styles.dateRow}>
                <Ionicons name="time-outline" size={14} color={colors.textMuted} />
                <Text style={styles.dateText}>{item.work_date} · {typeLabel}</Text>
              </View>
              {item.status === 'rejected' && item.decision_note ? (
                <Text style={styles.decisionNote}>Alasan penolakan: {item.decision_note}</Text>
              ) : null}
              {item.status === 'pending' ? (
                <TouchableOpacity onPress={() => confirmCancel(item)} style={styles.cancelBtn}>
                  <Text style={styles.cancelBtnText}>Batalkan</Text>
                </TouchableOpacity>
              ) : null}
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="time-outline" message="Belum ada pengajuan lembur." />}
      />

      {/* extraBottomOffset: Lembur adalah layar TAB — sama seperti Cuti. */}
      <Fab onPress={() => setFormVisible(true)} extraBottomOffset={68} />

      <FormSheet visible={formVisible} title="Ajukan Lembur" onClose={() => setFormVisible(false)}>
        <Text style={styles.hint}>
          Jam lembur dihitung otomatis dari bukti absensi pada tanggal yang dipilih — tidak diisi manual.
        </Text>
        <SelectChips label="Jenis Lembur" options={OVERTIME_TYPES} value={overtimeType} onChange={setOvertimeType} />
        <DateField label="Tanggal Kerja" value={workDate} onChange={setWorkDate} />
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
  hint: { ...type.tiny, marginBottom: spacing.md, lineHeight: 16 },
  decisionNote: { fontSize: 12.5, color: colors.danger, marginTop: spacing.xs },
  cancelBtn: { alignSelf: 'flex-start', marginTop: spacing.sm },
  cancelBtnText: { fontSize: 12.5, fontWeight: '700', color: colors.danger },
});
