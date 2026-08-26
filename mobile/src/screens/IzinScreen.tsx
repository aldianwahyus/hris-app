import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { FlatList, Image, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { IzinRequestRow, ListResponse } from '../api/types';
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
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { toIsoDate } from '../dateUtils';
import { colors, radius, spacing, type } from '../theme';

const CATEGORIES = [
  { value: 'sakit', label: 'Sakit' },
  { value: 'keperluan_keluarga', label: 'Keperluan Keluarga' },
  { value: 'lainnya', label: 'Lainnya' },
];

const CATEGORY_LABELS: Record<string, string> = {
  sakit: 'Sakit',
  keperluan_keluarga: 'Keperluan Keluarga',
  lainnya: 'Lainnya',
};

const STATUS_LABELS: Record<string, { label: string; tone: 'success' | 'warning' | 'danger' | 'neutral' }> = {
  pending: { label: 'Menunggu', tone: 'warning' },
  approved: { label: 'Disetujui', tone: 'success' },
  rejected: { label: 'Ditolak', tone: 'danger' },
};

interface Attachment {
  uri: string;
  name: string;
  mimeType: string;
}

export function IzinScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { showSuccess, showError } = useToast();
  // Admin HC (hr_approver) dikecualikan dari pembatasan tanggal mulai —
  // lihat SubmitIzinRequest::handle di backend (sumber kebenaran, ini
  // hanya UX klien).
  const isAdminHc = user?.roles.includes('hr_approver') ?? false;
  const [requests, setRequests] = useState<IzinRequestRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [category, setCategory] = useState('sakit');
  const [startDate, setStartDate] = useState<Date | null>(isAdminHc ? null : new Date());
  const [endDate, setEndDate] = useState<Date | null>(null);
  const [reason, setReason] = useState('');
  const [attachment, setAttachment] = useState<Attachment | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<IzinRequestRow>>('/izin')
      .then((response) => setRequests(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  function handleStartDateChange(date: Date) {
    setStartDate(date);

    if (endDate && date > endDate) {
      setEndDate(null);
    }
  }

  async function pickAttachment() {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      showError('Aktifkan izin galeri untuk melampirkan bukti.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      quality: 0.6,
    });

    if (!result.canceled && result.assets[0]) {
      const asset = result.assets[0];
      setAttachment({
        uri: asset.uri,
        name: asset.fileName ?? `lampiran-${Date.now()}.jpg`,
        mimeType: asset.mimeType ?? 'image/jpeg',
      });
    }
  }

  function resetForm() {
    setCategory('sakit');
    setStartDate(isAdminHc ? null : new Date());
    setEndDate(null);
    setReason('');
    setAttachment(null);
  }

  async function handleSubmit() {
    if (!startDate || !endDate || !reason) {
      showError('Tanggal dan alasan wajib diisi.');
      return;
    }

    if (category === 'sakit' && !attachment) {
      showError('Kategori Sakit wajib menyertakan lampiran bukti.');
      return;
    }

    setIsSubmitting(true);

    try {
      const formData = new FormData();
      formData.append('category', category);
      formData.append('start_date', toIsoDate(startDate));
      formData.append('end_date', toIsoDate(endDate));
      formData.append('reason', reason);

      if (attachment) {
        // React Native FormData menerima objek {uri,name,type} untuk
        // berkas lokal — bentuk ini tidak cocok dengan tipe web
        // File/Blob bawaan TypeScript, jadi type assertion di sini
        // sengaja dan aman (pola baku unggah berkas di RN+axios).
        formData.append('attachment', {
          uri: attachment.uri,
          name: attachment.name,
          type: attachment.mimeType,
        } as unknown as Blob);
      }

      const response = await apiClient.post<{ request_number: string }>('/izin', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      resetForm();
      setFormVisible(false);
      load();
      showSuccess(`Pengajuan izin ${response.data.request_number} terkirim.`);
    } catch (error) {
      showError(apiErrorMessage(error, 'Pengajuan izin ditolak.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Izin" subtitle="Izin tidak masuk bekerja — terpisah dari saldo Cuti" />
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
          const st = STATUS_LABELS[item.status] ?? { label: item.status, tone: 'neutral' as const };

          return (
            <Card style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.number}>{item.request_number}</Text>
                <Badge label={st.label} tone={st.tone} />
              </View>
              <Text style={styles.category}>{CATEGORY_LABELS[item.category] ?? item.category}</Text>
              <View style={styles.dateRow}>
                <Ionicons name="calendar-outline" size={14} color={colors.textMuted} />
                <Text style={styles.dateText}>
                  {item.start_date}
                  {item.start_date !== item.end_date ? ` — ${item.end_date}` : ''} · {item.total_days} hari
                </Text>
              </View>
              <Text style={styles.reason}>{item.reason}</Text>
              {item.attachment_path && (
                <View style={styles.attachedRow}>
                  <Ionicons name="attach-outline" size={13} color={colors.textMuted} />
                  <Text style={styles.attachedText}>Lampiran terpasang</Text>
                </View>
              )}
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="document-text-outline" message="Belum ada pengajuan izin." />}
      />

      <Fab onPress={() => setFormVisible(true)} />

      <FormSheet visible={formVisible} title="Ajukan Izin" onClose={() => setFormVisible(false)}>
        <Text style={styles.hint}>
          Izin TIDAK memotong saldo Cuti Tahunan — terpisah sepenuhnya. Kategori Sakit wajib
          menyertakan lampiran bukti (mis. foto surat dokter).
        </Text>
        <SelectChips label="Kategori" options={CATEGORIES} value={category} onChange={setCategory} />
        <DateField
          label={isAdminHc ? 'Tanggal Mulai' : 'Tanggal Mulai (hari ini)'}
          value={startDate}
          onChange={handleStartDateChange}
          minimumDate={isAdminHc ? undefined : startDate ?? undefined}
          maximumDate={isAdminHc ? undefined : startDate ?? undefined}
          disabled={!isAdminHc}
        />
        <DateField label="Tanggal Selesai" value={endDate} onChange={setEndDate} minimumDate={startDate ?? undefined} />
        <TextField label="Alasan" value={reason} onChangeText={setReason} placeholder="mis. Demam, istirahat di rumah" multiline />

        <Text style={styles.label}>Lampiran Bukti{category === 'sakit' ? ' (wajib)' : ' (opsional)'}</Text>
        {attachment ? (
          <View style={styles.attachmentPreview}>
            <Image source={{ uri: attachment.uri }} style={styles.previewImage} />
            <View style={{ flex: 1 }}>
              <Text style={styles.previewName} numberOfLines={1}>{attachment.name}</Text>
              <TouchableOpacity onPress={() => setAttachment(null)}>
                <Text style={styles.removeText}>Hapus</Text>
              </TouchableOpacity>
            </View>
          </View>
        ) : (
          <TouchableOpacity style={styles.pickButton} onPress={pickAttachment}>
            <Ionicons name="image-outline" size={18} color={colors.primary} />
            <Text style={styles.pickButtonText}>Pilih dari Galeri</Text>
          </TouchableOpacity>
        )}

        <PrimaryButton label="Kirim Pengajuan" onPress={handleSubmit} loading={isSubmitting} style={{ marginTop: spacing.lg }} />
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
  category: { ...type.caption, marginTop: 2 },
  dateRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, marginTop: 2 },
  dateText: { ...type.caption },
  reason: { fontSize: 12.5, color: colors.text, marginTop: spacing.xs },
  attachedRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: spacing.xs },
  attachedText: { fontSize: 11, color: colors.textMuted },
  hint: { ...type.tiny, marginBottom: spacing.md, lineHeight: 16 },
  label: { ...type.caption, marginBottom: spacing.xs },
  pickButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: colors.border,
    borderStyle: 'dashed',
    borderRadius: radius.md,
    paddingVertical: 14,
    marginBottom: spacing.md,
  },
  pickButtonText: { color: colors.primary, fontWeight: '700', fontSize: 13 },
  attachmentPreview: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginBottom: spacing.md,
    padding: spacing.sm,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
  },
  previewImage: { width: 44, height: 44, borderRadius: radius.sm },
  previewName: { fontSize: 12, color: colors.text, fontWeight: '600' },
  removeText: { fontSize: 11.5, color: colors.danger, fontWeight: '700', marginTop: 2 },
});
