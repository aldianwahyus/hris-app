import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useState } from 'react';
import {
  Image,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiErrorMessage } from '../api/client';
import { PrimaryButton } from '../components/PrimaryButton';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { colors, radius, spacing, type } from '../theme';

// TIDAK ADA field captcha di sini — login mobile melewati captcha
// sepenuhnya (ApiLoginRequest di backend), mengandalkan rate-limit
// ganda yang sudah berlaku di server.
export function LoginScreen() {
  const { login } = useAuth();
  const { showError } = useToast();
  const insets = useSafeAreaInsets();
  const [nrp, setNrp] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit() {
    if (!nrp || !password) {
      showError('NRP dan kata sandi wajib diisi.');
      return;
    }

    setIsSubmitting(true);

    try {
      await login(nrp.trim(), password);
    } catch (error) {
      showError(apiErrorMessage(error, 'NRP atau kata sandi salah.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <LinearGradient colors={[colors.primaryDark, colors.primary]} style={styles.container}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={{ flex: 1 }}
      >
        <View style={[styles.hero, { paddingTop: insets.top + spacing.xxl }]}>
          <View style={styles.logoMark}>
            <Image
              source={require('../../assets/logo-mark.png')}
              style={styles.logoImage}
              resizeMode="contain"
            />
          </View>
          <Text style={styles.bankName}>Bank NTB Syariah</Text>
          <Text style={styles.tagline}>HCIS Mobile — Employee Self Service</Text>
        </View>

        <View style={[styles.sheet, { paddingBottom: insets.bottom + spacing.xl }]}>
          <Text style={styles.title}>Masuk</Text>
          <Text style={styles.subtitle}>Gunakan NRP dan kata sandi akun HCIS Anda</Text>

          <View style={styles.field}>
            <Ionicons name="person-outline" size={18} color={colors.textMuted} />
            <TextInput
              style={styles.input}
              placeholder="NRP"
              placeholderTextColor={colors.textMuted}
              autoCapitalize="none"
              autoCorrect={false}
              value={nrp}
              onChangeText={setNrp}
            />
          </View>

          <View style={styles.field}>
            <Ionicons name="lock-closed-outline" size={18} color={colors.textMuted} />
            <TextInput
              style={styles.input}
              placeholder="Kata sandi"
              placeholderTextColor={colors.textMuted}
              secureTextEntry={!showPassword}
              value={password}
              onChangeText={setPassword}
            />
            <Ionicons
              name={showPassword ? 'eye-off-outline' : 'eye-outline'}
              size={18}
              color={colors.textMuted}
              onPress={() => setShowPassword((v) => !v)}
              suppressHighlighting
            />
          </View>

          <PrimaryButton label="Masuk" onPress={handleSubmit} loading={isSubmitting} style={styles.button} />
        </View>
      </KeyboardAvoidingView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  hero: { alignItems: 'center', paddingHorizontal: spacing.xl, paddingBottom: spacing.xxl },
  logoMark: {
    width: 64,
    height: 64,
    borderRadius: radius.lg,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
  },
  logoImage: { width: 42, height: 42 },
  bankName: { fontSize: 19, fontWeight: '800', color: '#fff' },
  tagline: { fontSize: 12.5, color: 'rgba(255,255,255,0.8)', marginTop: 3, fontWeight: '500' },
  sheet: {
    flex: 1,
    backgroundColor: colors.white,
    borderTopLeftRadius: radius.xl,
    borderTopRightRadius: radius.xl,
    padding: spacing.xl,
    paddingTop: spacing.xxl,
  },
  title: { ...type.h1 },
  subtitle: { ...type.caption, marginTop: 4, marginBottom: spacing.xl },
  field: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    marginBottom: spacing.md,
    backgroundColor: colors.background,
  },
  input: { flex: 1, paddingVertical: 14, fontSize: 14, color: colors.text },
  button: { marginTop: spacing.sm },
});
