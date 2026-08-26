import { Ionicons } from '@expo/vector-icons';
import React, { createContext, useCallback, useContext, useRef, useState } from 'react';
import { Animated, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius, shadow, spacing } from '../theme';

type ToastType = 'success' | 'error';

interface ToastState {
  id: number;
  message: string;
  type: ToastType;
}

interface ToastContextValue {
  showSuccess: (message: string) => void;
  showError: (message: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const TONE = {
  success: { bg: colors.primaryDark, icon: 'checkmark-circle' as const },
  error: { bg: colors.danger, icon: 'close-circle' as const },
};

const VISIBLE_MS = 2600;

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const insets = useSafeAreaInsets();
  const [toast, setToast] = useState<ToastState | null>(null);
  const opacity = useRef(new Animated.Value(0)).current;
  const translateY = useRef(new Animated.Value(-16)).current;
  const hideTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const nextId = useRef(0);

  const show = useCallback((message: string, type: ToastType) => {
    if (hideTimer.current) {
      clearTimeout(hideTimer.current);
    }

    const id = ++nextId.current;
    setToast({ id, message, type });
    opacity.setValue(0);
    translateY.setValue(-16);

    Animated.parallel([
      Animated.timing(opacity, { toValue: 1, duration: 200, useNativeDriver: true }),
      Animated.timing(translateY, { toValue: 0, duration: 200, useNativeDriver: true }),
    ]).start();

    hideTimer.current = setTimeout(() => {
      Animated.timing(opacity, { toValue: 0, duration: 200, useNativeDriver: true }).start(() => {
        setToast((current) => (current?.id === id ? null : current));
      });
    }, VISIBLE_MS);
  }, [opacity, translateY]);

  const showSuccess = useCallback((message: string) => show(message, 'success'), [show]);
  const showError = useCallback((message: string) => show(message, 'error'), [show]);

  return (
    <ToastContext.Provider value={{ showSuccess, showError }}>
      {children}
      {toast && (
        <Animated.View
          pointerEvents="none"
          style={[
            styles.toast,
            { top: insets.top + spacing.sm, backgroundColor: TONE[toast.type].bg, opacity, transform: [{ translateY }] },
          ]}
        >
          <Ionicons name={TONE[toast.type].icon} size={18} color="#fff" />
          <Text style={styles.text}>{toast.message}</Text>
        </Animated.View>
      )}
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  const context = useContext(ToastContext);

  if (!context) {
    throw new Error('useToast harus dipakai di dalam ToastProvider.');
  }

  return context;
}

const styles = StyleSheet.create({
  toast: {
    position: 'absolute',
    left: spacing.lg,
    right: spacing.lg,
    zIndex: 999,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: 12,
    paddingHorizontal: spacing.lg,
    borderRadius: radius.md,
    ...shadow.floating,
  },
  text: { flex: 1, color: '#fff', fontSize: 13, fontWeight: '700' },
});
