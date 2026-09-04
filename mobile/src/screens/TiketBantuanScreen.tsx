import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { HelpdeskTicketRow, ListResponse } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { EmptyState } from '../components/EmptyState';
import { Fab } from '../components/Fab';
import { FormSheet } from '../components/FormSheet';
import { PrimaryButton } from '../components/PrimaryButton';
import { ScreenHeader } from '../components/ScreenHeader';
import { SelectChips } from '../components/SelectChips';
import { TextField } from '../components/TextField';
import { useToast } from '../context/ToastContext';
import { MainStackParamList } from '../navigation/types';
import { colors, spacing, type } from '../theme';

const CATEGORIES = [
  { value: 'penggajian', label: 'Penggajian' },
  { value: 'absensi', label: 'Absensi' },
  { value: 'cuti_izin', label: 'Cuti/Izin' },
  { value: 'data_pegawai', label: 'Data Pegawai' },
  { value: 'akun_akses', label: 'Akun/Akses' },
  { value: 'lainnya', label: 'Lainnya' },
];

const PRIORITIES = [
  { value: 'rendah', label: 'Rendah' },
  { value: 'sedang', label: 'Sedang' },
  { value: 'tinggi', label: 'Tinggi' },
];

const STATUS_LABELS: Record<string, { label: string; tone: 'success' | 'warning' | 'danger' | 'neutral' }> = {
  terbuka: { label: 'Terbuka', tone: 'warning' },
  diproses: { label: 'Diproses', tone: 'warning' },
  selesai: { label: 'Selesai', tone: 'success' },
  ditutup: { label: 'Ditutup', tone: 'neutral' },
};

const PRIORITY_TONE: Record<string, 'success' | 'warning' | 'danger'> = {
  rendah: 'success',
  sedang: 'warning',
  tinggi: 'danger',
};

export function TiketBantuanScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<MainStackParamList>>();
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [tickets, setTickets] = useState<HelpdeskTicketRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [category, setCategory] = useState('lainnya');
  const [priority, setPriority] = useState('rendah');
  const [subject, setSubject] = useState('');
  const [description, setDescription] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<HelpdeskTicketRow>>('/bantuan')
      .then((response) => setTickets(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  function resetForm() {
    setCategory('lainnya');
    setPriority('rendah');
    setSubject('');
    setDescription('');
  }

  async function handleSubmit() {
    if (!subject.trim() || !description.trim()) {
      showError('Judul dan deskripsi wajib diisi.');
      return;
    }

    setIsSubmitting(true);

    try {
      await apiClient.post('/bantuan', { category, subject, description, priority });
      resetForm();
      setFormVisible(false);
      load();
      showSuccess('Tiket bantuan berhasil diajukan.');
    } catch (error) {
      showError(apiErrorMessage(error, 'Tiket tidak dapat diajukan.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Tiket Bantuan" subtitle="HR Helpdesk — pertanyaan & kendala Anda" />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={tickets}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + spacing.xl }]}
        onRefresh={async () => {
          setRefreshing(true);
          await load();
          setRefreshing(false);
        }}
        refreshing={refreshing}
        renderItem={({ item }) => {
          const st = STATUS_LABELS[item.status] ?? { label: item.status, tone: 'neutral' as const };

          return (
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={() => navigation.navigate('TiketBantuanDetail', { id: item.id })}
            >
              <Card style={styles.card}>
                <View style={styles.cardHeader}>
                  <Text style={styles.subject} numberOfLines={1}>{item.subject}</Text>
                  <Badge label={st.label} tone={st.tone} />
                </View>
                <Text style={styles.ticketNumber}>{item.ticket_number}</Text>
                <View style={styles.metaRow}>
                  <Badge label={PRIORITIES.find((p) => p.value === item.priority)?.label ?? item.priority} tone={PRIORITY_TONE[item.priority] ?? 'neutral'} />
                  <Ionicons name="chevron-forward" size={16} color={colors.textMuted} />
                </View>
              </Card>
            </TouchableOpacity>
          );
        }}
        ListEmptyComponent={<EmptyState icon="help-buoy-outline" message="Belum ada tiket bantuan." />}
      />

      <Fab onPress={() => setFormVisible(true)} />

      <FormSheet visible={formVisible} title="Ajukan Tiket Bantuan" onClose={() => setFormVisible(false)}>
        <SelectChips label="Kategori" options={CATEGORIES} value={category} onChange={setCategory} />
        <TextField label="Judul" value={subject} onChangeText={setSubject} placeholder="mis. Slip gaji belum muncul" />
        <TextField label="Deskripsi" value={description} onChangeText={setDescription} placeholder="Jelaskan kendala Anda" multiline />
        <SelectChips label="Prioritas" options={PRIORITIES} value={priority} onChange={setPriority} />
        <PrimaryButton label="Kirim Tiket" onPress={handleSubmit} loading={isSubmitting} style={{ marginTop: spacing.lg }} />
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
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: spacing.sm },
  subject: { ...type.body, fontWeight: '700', flex: 1 },
  ticketNumber: { ...type.caption },
  metaRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: spacing.xs },
});
