import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import { AuthUser } from './types';

const TOKEN_KEY = 'hcis_token';
const USER_KEY = 'hcis_user';

// expo-secure-store TIDAK punya implementasi web (Keychain/Keystore
// adalah konsep native) — memanggilnya di web melempar TypeError yang
// membuat AuthProvider macet permanen di layar loading. Web (dipakai
// untuk preview browser, bukan target produksi) jatuh ke localStorage;
// perangkat asli (iOS/Android via Expo Go) tetap pakai SecureStore.
const storage = Platform.OS === 'web'
  ? {
      getItem: async (key: string): Promise<string | null> => window.localStorage.getItem(key),
      setItem: async (key: string, value: string): Promise<void> => {
        window.localStorage.setItem(key, value);
      },
      deleteItem: async (key: string): Promise<void> => {
        window.localStorage.removeItem(key);
      },
    }
  : {
      getItem: SecureStore.getItemAsync,
      setItem: SecureStore.setItemAsync,
      deleteItem: SecureStore.deleteItemAsync,
    };

const baseURL = process.env.EXPO_PUBLIC_API_URL;

if (!baseURL) {
  throw new Error(
    'EXPO_PUBLIC_API_URL belum diset — salin mobile/.env.example ke mobile/.env dan isi IP LAN mesin dev.',
  );
}

export const apiClient = axios.create({ baseURL });

apiClient.interceptors.request.use(async (config) => {
  const token = await storage.getItem(TOKEN_KEY);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

let onUnauthorized: (() => void) | null = null;

export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler;
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Hapus token DAN data pengguna tersimpan (bukan cuma token) —
      // kalau hanya token yang dihapus, USER_KEY basi tertinggal di
      // storage sampai login berikutnya menimpanya.
      await storage.deleteItem(TOKEN_KEY);
      await storage.deleteItem(USER_KEY);
      onUnauthorized?.();
    }

    return Promise.reject(error);
  },
);

/**
 * Token DAN identitas pengguna disimpan bersamaan saat login — bukan
 * dipulihkan lewat GET /user saat aplikasi dibuka lagi, karena /user
 * mengembalikan model User Eloquent apa adanya (id/name/email/employee_id),
 * BUKAN bentuk {nrp,nama,roles} yang dihasilkan TokenController::store().
 * Dua bentuk itu sengaja berbeda di backend (lihat catatan di
 * TokenController) — client tidak boleh mengasumsikan keduanya sama.
 */
export async function storeSession(token: string, user: AuthUser): Promise<void> {
  await storage.setItem(TOKEN_KEY, token);
  await storage.setItem(USER_KEY, JSON.stringify(user));
}

export async function readSession(): Promise<{ token: string; user: AuthUser } | null> {
  const [token, userJson] = await Promise.all([storage.getItem(TOKEN_KEY), storage.getItem(USER_KEY)]);

  if (!token || !userJson) {
    return null;
  }

  return { token, user: JSON.parse(userJson) as AuthUser };
}

export async function clearSession(): Promise<void> {
  await storage.deleteItem(TOKEN_KEY);
  await storage.deleteItem(USER_KEY);
}

/** Pesan error backend seragam: {"message": "..."} pada 4xx/5xx (lihat controller API). */
export function apiErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error) && typeof error.response?.data?.message === 'string') {
    return error.response.data.message;
  }

  return fallback;
}
