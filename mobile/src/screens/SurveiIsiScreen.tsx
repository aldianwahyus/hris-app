import { Ionicons } from '@expo/vector-icons';
import { useCallback, useState } from 'react';
import { RouteProp, useFocusEffect, useNavigation, useRoute } from '@react-navigation/native';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiClient, apiErrorMessage } from '../api/client';
import { SurveyDetailResponse, SurveyQuestionRow } from '../api/types';
import { PrimaryButton } from '../components/PrimaryButton';
import { ScreenHeader } from '../components/ScreenHeader';
import { SelectChips } from '../components/SelectChips';
import { TextField } from '../components/TextField';
import { useToast } from '../context/ToastContext';
import { MainStackParamList } from '../navigation/types';
import { colors, spacing, type } from '../theme';

type IsiRouteProp = RouteProp<MainStackParamList, 'SurveiIsi'>;

const NPS_OPTIONS = Array.from({ length: 11 }, (_, i) => ({ value: String(i), label: String(i) }));
const RATING_OPTIONS = Array.from({ length: 5 }, (_, i) => ({ value: String(i + 1), label: String(i + 1) }));

export function SurveiIsiScreen() {
  const route = useRoute<IsiRouteProp>();
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { showSuccess, showError } = useToast();
  const [title, setTitle] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [questions, setQuestions] = useState<SurveyQuestionRow[]>([]);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(() => {
    return apiClient
      .get<SurveyDetailResponse>(`/survei/${route.params.id}`)
      .then((response) => {
        setTitle(response.data.data.title);
        setIsAnonymous(response.data.data.is_anonymous);
        setQuestions(response.data.questions.sort((a, b) => a.display_order - b.display_order));
      })
      .catch(() => {
        showError('Survei tidak ditemukan atau sudah pernah diisi.');
        navigation.goBack();
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [route.params.id]);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  function setAnswer(questionId: string, value: string) {
    setAnswers((prev) => ({ ...prev, [questionId]: value }));
  }

  async function handleSubmit() {
    const unanswered = questions.find((q) => !answers[q.id]?.trim());

    if (unanswered) {
      showError(`Pertanyaan "${unanswered.question_text}" wajib dijawab.`);
      return;
    }

    setIsSubmitting(true);

    try {
      await apiClient.post(`/survei/${route.params.id}/isi`, { jawaban: answers });
      showSuccess('Terima kasih, jawaban Anda telah terkirim.');
      navigation.goBack();
    } catch (error) {
      showError(apiErrorMessage(error, 'Jawaban tidak dapat dikirim.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerWrap}>
        <ScreenHeader title={title || 'Isi Survei'} subtitle={isAnonymous ? 'Jawaban Anda bersifat anonim' : undefined} />
        <TouchableOpacity style={[styles.back, { top: insets.top + spacing.lg }]} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <ScrollView contentContainerStyle={[styles.body, { paddingBottom: insets.bottom + spacing.xxl }]}>
        {questions.map((question) => (
          <View key={question.id} style={styles.questionBlock}>
            <Text style={styles.questionText}>{question.question_text}</Text>

            {question.question_type === 'nps_0_10' && (
              <SelectChips label="" options={NPS_OPTIONS} value={answers[question.id] ?? null} onChange={(v) => setAnswer(question.id, v)} />
            )}
            {question.question_type === 'rating_1_5' && (
              <SelectChips label="" options={RATING_OPTIONS} value={answers[question.id] ?? null} onChange={(v) => setAnswer(question.id, v)} />
            )}
            {question.question_type === 'pilihan_ganda' && (
              <SelectChips
                label=""
                options={question.options.map((o) => ({ value: o, label: o }))}
                value={answers[question.id] ?? null}
                onChange={(v) => setAnswer(question.id, v)}
              />
            )}
            {question.question_type === 'teks' && (
              <TextField
                label=""
                value={answers[question.id] ?? ''}
                onChangeText={(v) => setAnswer(question.id, v)}
                placeholder="Tulis jawaban Anda..."
                multiline
              />
            )}
          </View>
        ))}

        {questions.length > 0 && (
          <PrimaryButton label="Kirim Jawaban" onPress={handleSubmit} loading={isSubmitting} style={{ marginTop: spacing.md }} />
        )}
      </ScrollView>
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
  body: { padding: spacing.xl, gap: spacing.lg },
  questionBlock: { gap: spacing.sm },
  questionText: { ...type.body, fontWeight: '700' },
});
