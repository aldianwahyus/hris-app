import { Ionicons } from '@expo/vector-icons';
import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { Alert, FlatList, Image, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
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
  cancelled: { label: 'Dibatalkan', tone: 'neutral' },
};

interface Attachment {
  uri: string;
  name: string;
  mimeType: string;
}

// Format & ukuran SAMA PERSIS validasi backend (SubmitIzinRequestForm::rules()
// — mimes:jpg,jpeg,png,pdf, max:5120 KB) supaya penolakan tidak baru
// diketahui pengguna setelah kirim ke server.
const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;
const MAX_ATTACHMENT_LABEL = 'JPG, PNG, atau PDF — maks 5 MB';

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

  function confirmCancel(item: IzinRequestRow) {
    Alert.alert('Batalkan Pengajuan', `Batalkan pengajuan izin ${item.request_number}?`, [
      { text: 'Tidak', style: 'cancel' },
      { text: 'Ya, Batalkan', style: 'destructive', onPress: () => cancelRequest(item.id) },
    ]);
  }

  async function cancelRequest(id: string) {
    try {
      await apiClient.post(`/izin/${id}/batal`);
      showSuccess('Pengajuan izin berhasil dibatalkan.');
      load();
    } catch (error) {
      showError(apiErrorMessage(error, 'Pengajuan tidak dapat dibatalkan.'));
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

    if (result.canceled || !result.assets[0]) {
      return;
    }

    const asset = result.assets[0];

    if (asset.fileSize && asset.fileSize > MAX_ATTACHMENT_BYTES) {
      showError(`Ukuran gambar melebihi 5 MB (${MAX_ATTACHMENT_LABEL}).`);
      return;
    }

    setAttachment({
      uri: asset.uri,
      name: asset.fileName ?? `lampiran-${Date.now()}.jpg`,
      mimeType: asset.mimeType ?? 'image/jpeg',
    });
  }

  async function pickDocument() {
    const result = await DocumentPicker.getDocumentAsync({
      type: 'application/pdf',
      copyToCacheDirectory: true,
    });

    if (result.canceled || !result.assets[0]) {
      return;
    }

    const asset = result.assets[0];

    if (asset.size && asset.size > MAX_ATTACHMENT_BYTES) {
      showError(`Ukuran berkas melebihi 5 MB (${MAX_ATTACHMENT_LABEL}).`);
      return;
    }

    setAttachment({
      uri: asset.uri,
      name: asset.name,
      mimeType: asset.mimeType ?? 'application/pdf',
    });
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
        <Text style={styles.formatHint}>{MAX_ATTACHMENT_LABEL}</Text>
        {attachment ? (
          <View style={styles.attachmentPreview}>
            {attachment.mimeType === 'application/pdf' ? (
              <View style={styles.previewPdfIcon}>
                <Ionicons name="document-text" size={22} color={colors.primary} />
              </View>
            ) : (
              <Image source={{ uri: attachment.uri }} style={styles.previewImage} />
            )}
            <View style={{ flex: 1 }}>
              <Text style={styles.previewName} numberOfLines={1}>{attachment.name}</Text>
              <TouchableOpacity onPress={() => setAttachment(null)}>
                <Text style={styles.removeText}>Hapus</Text>
              </TouchableOpacity>
            </View>
          </View>
        ) : (
          <View style={styles.pickRow}>
            <TouchableOpacity style={[styles.pickButton, { flex: 1 }]} onPress={pickAttachment}>
              <Ionicons name="image-outline" size={18} color={colors.primary} />
              <Text style={styles.pickButtonText}>Galeri</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[styles.pickButton, { flex: 1 }]} onPress={pickDocument}>
              <Ionicons name="document-outline" size={18} color={colors.primary} />
              <Text style={styles.pickButtonText}>Dokumen PDF</Text>
            </TouchableOpacity>
          </View>
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
  pickRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  formatHint: { ...type.tiny, marginBottom: spacing.sm, marginTop: -2 },
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
  previewPdfIcon: {
    width: 44,
    height: 44,
    borderRadius: radius.sm,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  previewName: { fontSize: 12, color: colors.text, fontWeight: '600' },
  removeText: { fontSize: 11.5, color: colors.danger, fontWeight: '700', marginTop: 2 },
  decisionNote: { fontSize: 12.5, color: colors.danger, marginTop: spacing.xs },
  cancelBtn: { alignSelf: 'flex-start', marginTop: spacing.sm },
  cancelBtnText: { fontSize: 12.5, fontWeight: '700', color: colors.danger },
});
