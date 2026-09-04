import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { RouteProp, useFocusEffect, useNavigation, useRoute } from '@react-navigation/native';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { HelpdeskDetailResponse, HelpdeskReplyRow, HelpdeskTicketRow } from '../api/types';
import { Badge } from '../components/Badge';
import { Card } from '../components/Card';
import { PrimaryButton } from '../components/PrimaryButton';
import { ScreenHeader } from '../components/ScreenHeader';
import { useToast } from '../context/ToastContext';
import { MainStackParamList } from '../navigation/types';
import { colors, radius, spacing, type } from '../theme';

type DetailRouteProp = RouteProp<MainStackParamList, 'TiketBantuanDetail'>;

const STATUS_LABELS: Record<string, { label: string; tone: 'success' | 'warning' | 'danger' | 'neutral' }> = {
  terbuka: { label: 'Terbuka', tone: 'warning' },
  diproses: { label: 'Diproses', tone: 'warning' },
  selesai: { label: 'Selesai', tone: 'success' },
  ditutup: { label: 'Ditutup', tone: 'neutral' },
};

// Balasan pegawai pengaju pada tiket 'selesai' otomatis membuka lagi
// statusnya (lihat ReplyTicket::handle backend) — reload setelah kirim
// balasan supaya status termutakhirkan di layar ini.
export function TiketBantuanDetailScreen() {
  const route = useRoute<DetailRouteProp>();
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { showError } = useToast();
  const [ticket, setTicket] = useState<HelpdeskTicketRow | null>(null);
  const [replies, setReplies] = useState<HelpdeskReplyRow[]>([]);
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<HelpdeskDetailResponse>(`/bantuan/${route.params.id}`)
      .then((response) => {
        setTicket(response.data.data);
        setReplies(response.data.replies);
      })
      .catch(() => {});
  }, [route.params.id]);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  async function handleReply() {
    if (!message.trim()) {
      return;
    }

    setIsSubmitting(true);

    try {
      await apiClient.post(`/bantuan/${route.params.id}/balas`, { message });
      setMessage('');
      load();
    } catch (error) {
      showError(apiErrorMessage(error, 'Balasan tidak dapat dikirim.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  const st = ticket ? STATUS_LABELS[ticket.status] ?? { label: ticket.status, tone: 'neutral' as const } : null;

  return (
    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={styles.headerWrap}>
        <ScreenHeader title={ticket?.ticket_number ?? 'Tiket Bantuan'} subtitle={ticket?.subject} />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <ScrollView contentContainerStyle={styles.body}>
        {ticket && (
          <Card style={styles.ticketCard}>
            <View style={styles.ticketHeader}>
              <Text style={styles.ticketSubject}>{ticket.subject}</Text>
              {st ? <Badge label={st.label} tone={st.tone} /> : null}
            </View>
            <Text style={styles.description}>{ticket.description}</Text>
          </Card>
        )}

        {replies.map((reply, index) => (
          <Card key={`${reply.created_at}-${index}`} style={styles.replyCard}>
            <View style={styles.replyHeader}>
              <Text style={styles.replyAuthor}>{reply.author_name}</Text>
              <Text style={styles.replyTime}>{reply.created_at}</Text>
            </View>
            <Text style={styles.replyMessage}>{reply.message}</Text>
          </Card>
        ))}

        {replies.length === 0 && ticket && (
          <Text style={styles.emptyReplies}>Belum ada balasan.</Text>
        )}
      </ScrollView>

      {ticket?.status !== 'ditutup' && (
        <View style={[styles.composer, { paddingBottom: insets.bottom + spacing.md }]}>
          <TextInput
            style={styles.composerInput}
            value={message}
            onChangeText={setMessage}
            placeholder="Tulis balasan..."
            placeholderTextColor={colors.textMuted}
            multiline
          />
          <PrimaryButton label="Kirim" onPress={handleReply} loading={isSubmitting} style={styles.sendButton} />
        </View>
      )}
    </KeyboardAvoidingView>
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
  body: { padding: spacing.xl, gap: spacing.md },
  ticketCard: { gap: spacing.xs },
  ticketHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: spacing.sm },
  ticketSubject: { ...type.h2, fontSize: 15, flex: 1 },
  description: { fontSize: 13, color: colors.text, marginTop: spacing.xs, lineHeight: 19 },
  replyCard: { gap: 4 },
  replyHeader: { flexDirection: 'row', justifyContent: 'space-between' },
  replyAuthor: { ...type.caption, fontWeight: '700', color: colors.text },
  replyTime: { ...type.tiny },
  replyMessage: { fontSize: 13, color: colors.text, lineHeight: 19 },
  emptyReplies: { ...type.caption, textAlign: 'center', marginTop: spacing.lg },
  composer: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: spacing.sm,
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    backgroundColor: colors.white,
  },
  composerInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    fontSize: 13.5,
    color: colors.text,
    backgroundColor: colors.white,
    maxHeight: 90,
  },
  sendButton: { paddingHorizontal: spacing.lg, minWidth: 78 },
});
