import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import * as Location from 'expo-location';
import { ActivityIndicator, FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { AttendanceAction, AttendanceRecordRow, AttendanceSubmitResponse, ListResponse } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { EmptyState } from '../components/EmptyState';
import { ScreenHeader } from '../components/ScreenHeader';
import { useToast } from '../context/ToastContext';
import { toIsoDate } from '../dateUtils';
import { attendanceStatus } from '../statusLabels';
import { colors, radius, spacing, type } from '../theme';

const ACTION_META: Record<AttendanceAction, { label: string; icon: keyof typeof Ionicons.glyphMap; toast: string }> = {
  masuk: { label: 'Absen Masuk', icon: 'log-in-outline', toast: 'Absen masuk tercatat.' },
  istirahat: { label: 'Mulai Istirahat', icon: 'cafe-outline', toast: 'Mulai istirahat tercatat. Selamat istirahat!' },
  kembali: { label: 'Kembali dari Istirahat', icon: 'return-down-back-outline', toast: 'Kembali dari istirahat tercatat.' },
  pulang: { label: 'Absen Pulang', icon: 'log-out-outline', toast: 'Absen pulang tercatat. Sampai jumpa besok!' },
};

// Server yang memutuskan sah/tidaknya lokasi (geofence kantor) DAN
// urutan aksi (mis. tidak bisa Kembali sebelum Istirahat) — klien HANYA
// mengirim koordinat GPS mentah + aksi yang dipilih pengguna, tidak
// menyimpulkan/menegakkan urutan sendiri. Istirahat/Kembali OPSIONAL
// (boleh langsung Masuk→Pulang), TAPI begitu Istirahat tercatat, Pulang
// disembunyikan sampai Kembali juga tercatat — lihat RecordGpsAttendance.
export function AttendanceScreen() {
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [records, setRecords] = useState<AttendanceRecordRow[]>([]);
  const [submittingAction, setSubmittingAction] = useState<AttendanceAction | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const loadRecords = useCallback(() => {
    return apiClient
      .get<ListResponse<AttendanceRecordRow>>('/absensi')
      .then((response) => setRecords(response.data.data))
      .catch(() => {});
  }, []);

  useFocusEffect(useCallback(() => {
    loadRecords();
  }, [loadRecords]));

  async function handleRefresh() {
    setRefreshing(true);
    await loadRecords();
    setRefreshing(false);
  }

  // toIsoDate() (bukan toISOString().slice()) — hindari selisih UTC vs
  // waktu lokal Indonesia menggeser "hari ini" jadi hari kemarin.
  const today = records[0]?.work_date === toIsoDate(new Date()) ? records[0] : null;

  const belumMasuk = !today?.check_in_at;
  const sudahPulang = !!today?.check_out_at;
  const sedangIstirahat = !!today?.break_start_at && !today?.break_end_at;

  const availableActions: AttendanceAction[] = sudahPulang
    ? []
    : belumMasuk
      ? ['masuk']
      : sedangIstirahat
        ? ['kembali']
        : ['istirahat', 'pulang'];

  async function handleAction(action: AttendanceAction) {
    setSubmittingAction(action);

    try {
      const { status } = await Location.requestForegroundPermissionsAsync();

      if (status !== 'granted') {
        showError('Aktifkan izin lokasi untuk mencatat absensi.');
        return;
      }

      const position = await Location.getCurrentPositionAsync({});

      const response = await apiClient.post<AttendanceSubmitResponse>('/absensi', {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        action,
      });

      showSuccess(ACTION_META[response.data.action].toast);
      loadRecords();
    } catch (error) {
      showError(apiErrorMessage(error, 'Absensi tidak dapat dicatat.'));
    } finally {
      setSubmittingAction(null);
    }
  }

  return (
    <View style={styles.container}>
      <ScreenHeader title="Absensi" subtitle="Catat kehadiran lewat lokasi GPS" />

      <FlatList
        data={records.slice(today ? 1 : 0)}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + 100 }]}
        ListHeaderComponent={
          <>
            <Card style={styles.statusCard}>
              <View style={styles.statusRow}>
                <View style={styles.statusIconWrap}>
                  <Ionicons name="today-outline" size={20} color={colors.primary} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.statusLabel}>Hari ini</Text>
                  <Text style={styles.statusTimes}>
                    Masuk {today?.check_in_at ? formatTime(today.check_in_at) : '—'} · Pulang{' '}
                    {today?.check_out_at ? formatTime(today.check_out_at) : '—'}
                  </Text>
                  {(today?.break_start_at || today?.break_end_at) && (
                    <Text style={styles.statusBreak}>
                      Istirahat {today?.break_start_at ? formatTime(today.break_start_at) : '—'} · Kembali{' '}
                      {today?.break_end_at ? formatTime(today.break_end_at) : '—'}
                    </Text>
                  )}
                </View>
              </View>

              {sudahPulang ? (
                <View style={styles.doneRow}>
                  <Ionicons name="checkmark-circle" size={20} color={colors.primaryDark} />
                  <Text style={styles.doneText}>Absensi Hari Ini Lengkap</Text>
                </View>
              ) : (
                <View style={styles.actionRow}>
                  {availableActions.map((action) => {
                    const meta = ACTION_META[action];
                    const isPrimary = action === 'masuk' || action === 'kembali' || action === 'pulang';
                    const isLoading = submittingAction === action;

                    return (
                      <TouchableOpacity
                        key={action}
                        style={[
                          styles.actionButton,
                          isPrimary ? styles.actionButtonPrimary : styles.actionButtonSecondary,
                          availableActions.length > 1 && styles.actionButtonFlex,
                        ]}
                        onPress={() => handleAction(action)}
                        disabled={submittingAction !== null}
                        activeOpacity={0.85}
                      >
                        {isLoading ? (
                          <ActivityIndicator color={isPrimary ? '#fff' : colors.primary} />
                        ) : (
                          <>
                            <Ionicons name={meta.icon} size={18} color={isPrimary ? '#fff' : colors.primary} />
                            <Text style={isPrimary ? styles.actionTextPrimary : styles.actionTextSecondary}>
                              {meta.label}
                            </Text>
                          </>
                        )}
                      </TouchableOpacity>
                    );
                  })}
                </View>
              )}
            </Card>

            <Text style={styles.sectionTitle}>Riwayat</Text>
          </>
        }
        renderItem={({ item }) => {
          const st = attendanceStatus(item.status);

          return (
            <Card style={styles.row}>
              <View style={styles.rowIcon}>
                <Ionicons name="calendar-outline" size={16} color={colors.textMuted} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.rowDate}>{formatDateShort(item.work_date)}</Text>
                <Text style={styles.rowDetail}>
                  {item.check_in_at ? formatTime(item.check_in_at) : '—'} – {item.check_out_at ? formatTime(item.check_out_at) : '—'}
                  {item.break_start_at ? ` · Istirahat ${formatTime(item.break_start_at)}` : ''}
                  {item.break_end_at ? `–${formatTime(item.break_end_at)}` : ''}
                </Text>
              </View>
              <Badge label={st.label} tone={st.tone} />
            </Card>
          );
        }}
        onRefresh={handleRefresh}
        refreshing={refreshing}
        ListEmptyComponent={<EmptyState icon="finger-print-outline" message="Belum ada riwayat absensi." />}
      />
    </View>
  );
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatDateShort(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.xl, gap: spacing.md },
  statusCard: { gap: spacing.lg },
  statusRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  statusIconWrap: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    backgroundColor: colors.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statusLabel: { ...type.caption },
  statusTimes: { ...type.h2, fontSize: 15, marginTop: 2 },
  statusBreak: { ...type.tiny, marginTop: 2 },
  actionRow: { flexDirection: 'row', gap: spacing.sm },
  actionButton: {
    flexDirection: 'row',
    gap: spacing.sm,
    borderRadius: radius.md,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionButtonFlex: { flex: 1 },
  actionButtonPrimary: { backgroundColor: colors.primary },
  actionButtonSecondary: { backgroundColor: colors.white, borderWidth: 1.5, borderColor: colors.primary },
  actionTextPrimary: { color: '#fff', fontWeight: '700', fontSize: 13.5 },
  actionTextSecondary: { color: colors.primary, fontWeight: '700', fontSize: 13.5 },
  doneRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  doneText: { color: colors.primaryDark, fontWeight: '700', fontSize: 14 },
  sectionTitle: { ...type.caption, textTransform: 'uppercase', letterSpacing: 0.6, marginTop: spacing.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginBottom: 0 },
  rowIcon: {
    width: 34,
    height: 34,
    borderRadius: radius.sm,
    backgroundColor: colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowDate: { fontSize: 13.5, fontWeight: '700', color: colors.text, textTransform: 'capitalize' },
  rowDetail: { ...type.tiny, marginTop: 2 },
});
