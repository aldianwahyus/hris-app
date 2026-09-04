import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { DocumentRequestRow, ListResponse } from '../api/types';
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
import { colors, spacing, type } from '../theme';

const DOCUMENT_TYPES = [
  { value: 'surat_keterangan_kerja', label: 'Surat Keterangan Kerja' },
  { value: 'surat_referensi', label: 'Surat Referensi Kerja' },
  { value: 'surat_keterangan_penghasilan', label: 'Surat Keterangan Penghasilan' },
  { value: 'lainnya', label: 'Lainnya' },
];

const DOCUMENT_TYPE_LABELS: Record<string, string> = Object.fromEntries(
  DOCUMENT_TYPES.map((d) => [d.value, d.label]),
);

const STATUS_LABELS: Record<string, { label: string; tone: 'success' | 'warning' | 'danger' | 'neutral' }> = {
  pending: { label: 'Menunggu', tone: 'warning' },
  diproses: { label: 'Diproses', tone: 'warning' },
  siap: { label: 'Siap Diunduh', tone: 'success' },
  ditolak: { label: 'Ditolak', tone: 'danger' },
};

// Ajukan + riwayat. TIDAK ada unduh PDF di mobile (pola SAMA Slip Gaji,
// lihat PayslipScreen) — dokumen 'siap' diunduh lewat aplikasi web.
export function AjukanDokumenScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [requests, setRequests] = useState<DocumentRequestRow[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [documentType, setDocumentType] = useState('surat_keterangan_kerja');
  const [purpose, setPurpose] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<ListResponse<DocumentRequestRow>>('/dokumen')
      .then((response) => setRequests(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  async function handleSubmit() {
    if (!purpose.trim()) {
      showError('Keperluan wajib diisi.');
      return;
    }

    setIsSubmitting(true);

    try {
      await apiClient.post('/dokumen', { document_type: documentType, purpose });
      setPurpose('');
      setFormVisible(false);
      load();
      showSuccess('Permintaan dokumen terkirim, menunggu diproses HC.');
    } catch (error) {
      showError(apiErrorMessage(error, 'Permintaan dokumen ditolak.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title="Layanan Dokumen Mandiri" subtitle="Ajukan surat keterangan kerja & sejenisnya" />
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
                <Text style={styles.docType}>{DOCUMENT_TYPE_LABELS[item.document_type] ?? item.document_type}</Text>
                <Badge label={st.label} tone={st.tone} />
              </View>
              <Text style={styles.purpose}>{item.purpose}</Text>
              {item.status === 'ditolak' && item.decision_note ? (
                <Text style={styles.decisionNote}>Alasan penolakan: {item.decision_note}</Text>
              ) : null}
              {item.status === 'siap' ? (
                <Text style={styles.readyHint}>Sudah terbit — unduh lewat aplikasi web.</Text>
              ) : null}
            </Card>
          );
        }}
        ListEmptyComponent={<EmptyState icon="document-text-outline" message="Belum ada permintaan dokumen." />}
      />

      <Fab onPress={() => setFormVisible(true)} />

      <FormSheet visible={formVisible} title="Ajukan Dokumen" onClose={() => setFormVisible(false)}>
        <SelectChips label="Jenis Dokumen" options={DOCUMENT_TYPES} value={documentType} onChange={setDocumentType} />
        <TextField label="Keperluan" value={purpose} onChangeText={setPurpose} placeholder="mis. Persyaratan pengajuan KPR" multiline />
        <PrimaryButton label="Kirim Permintaan" onPress={handleSubmit} loading={isSubmitting} style={{ marginTop: spacing.lg }} />
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
  docType: { ...type.body, fontWeight: '700', flex: 1, marginRight: spacing.sm },
  purpose: { fontSize: 12.5, color: colors.text, marginTop: spacing.xs },
  decisionNote: { fontSize: 12.5, color: colors.danger, marginTop: spacing.xs },
  readyHint: { fontSize: 12.5, color: colors.primaryDark, marginTop: spacing.xs, fontWeight: '600' },
});
