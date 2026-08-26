import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { ListResponse, SppdRequestRow } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { DateField } from '../components/DateField';
import { EmptyState } from '../components/EmptyState';
import { Fab } from '../components/Fab';
import { FormSheet } from '../components/FormSheet';
import { PrimaryButton } from '../components/PrimaryButton';
import { ScreenHeader } from '../components/ScreenHeader';
import { SelectChips } from '../components/SelectChips';
import { TextField } from '../components/TextField';
import { useToast } from '../context/ToastContext';
import { toIsoDate } from '../dateUtils';
import { requestStatus } from '../statusLabels';
import { colors, spacing, type } from '../theme';

const TRIP_CATEGORIES = [
  { value: 'jarak_pendek', label: 'Jarak Pendek' },
  { value: 'jarak_jauh_dalam_provinsi', label: 'Jauh — Dalam Provinsi' },
  { value: 'jarak_jauh_keluar_provinsi', label: 'Jauh — Keluar Provinsi' },
  { value: 'luar_negeri', label: 'Luar Negeri' },
  { value: 'pindah', label: 'Pindah Tugas' },
  { value: 'detasir', label: 'Detasir' },
];

const RADIUS_BANDS = [
  { value: '30_100', label: '30–100 km' },
  { value: '100_150', label: '100–150 km' },
  { value: '150_plus', label: '> 150 km' },
];

export function SppdScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [requests, setRequests] = useState<SppdRequestRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [tripCategory, setTripCategory] = useState(TRIP_CATEGORIES[0].value);
  const [radiusBand, setRadiusBand] = useState('');
  const [destination, setDestination] = useState('');
  const [purpose, setPurpose] = useState('');
  const [startDate, setStartDate] = useState<Date | null>(null);
  const [endDate, setEndDate] = useState<Date | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<SppdRequestRow>>('/sppd')
      .then((response) => setRequests(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  function handleStartDateChange(date: Date) {
    setStartDate(date);

    // Sama seperti Cuti — cegah tanggal selesai yang sudah usang
    // (lebih awal dari tanggal mulai baru) daripada ditolak backend
    // tanpa penjelasan jelas ke pengguna.
    if (endDate && date > endDate) {
      setEndDate(null);
    }
  }

  async function handleSubmit() {
    if (!destination || !purpose || !startDate || !endDate) {
      showError('Tujuan, keperluan, dan tanggal wajib diisi.');
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await apiClient.post<{ request_number: string }>('/sppd', {
        trip_category: tripCategory,
        destination,
        purpose,
        start_date: toIsoDate(startDate),
        end_date: toIsoDate(endDate),
        radius_band: radiusBand || undefined,
      });
      setDestination('');
      setPurpose('');
      setStartDate(null);
      setEndDate(null);
      setFormVisible(false);
      load();
      showSuccess(`Pengajuan SPPD ${response.data.request_number} terkirim.`);
    } catch (error) {
      showError(apiErrorMessage(error, 'Pengajuan SPPD ditolak.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="SPPD" subtitle="Surat Perintah Perjalanan Dinas" />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={requests}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + spacing.xl }]}
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
                <Ionicons name="location-outline" size={14} color={colors.textMuted} />
                <Text style={styles.dateText}>{item.destination}</Text>
              </View>
              <View style={styles.dateRow}>
                <Ionicons name="calendar-outline" size={14} color={colors.textMuted} />
                <Text style={styles.dateText}>{item.start_date} — {item.end_date}</Text>
              </View>
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="airplane-outline" message="Belum ada pengajuan SPPD." />}
      />

      <Fab onPress={() => setFormVisible(true)} />

      <FormSheet visible={formVisible} title="Ajukan SPPD" onClose={() => setFormVisible(false)}>
        <SelectChips label="Kategori Perjalanan" options={TRIP_CATEGORIES} value={tripCategory} onChange={setTripCategory} />
        {tripCategory === 'jarak_pendek' && (
          <SelectChips label="Rentang Jarak" options={RADIUS_BANDS} value={radiusBand} onChange={setRadiusBand} clearable />
        )}
        <TextField label="Tujuan" value={destination} onChangeText={setDestination} placeholder="mis. Kantor Cabang Mataram" />
        <TextField label="Keperluan" value={purpose} onChangeText={setPurpose} placeholder="mis. Rapat koordinasi" />
        <DateField label="Tanggal Mulai" value={startDate} onChange={handleStartDateChange} minimumDate={new Date()} />
        <DateField label="Tanggal Selesai" value={endDate} onChange={setEndDate} minimumDate={startDate ?? new Date()} />
        <PrimaryButton label="Kirim Pengajuan" onPress={handleSubmit} loading={isSubmitting} style={{ marginTop: spacing.sm }} />
      </FormSheet>
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
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  number: { ...type.body, fontWeight: '700' },
  dateRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, marginTop: 2 },
  dateText: { ...type.caption },
});
