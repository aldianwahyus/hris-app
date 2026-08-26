import React, { createContext, useContext, useEffect, useState } from 'react';
import { apiClient, clearSession, readSession, setUnauthorizedHandler, storeSession } from '../api/client';
import { AuthResponse, AuthUser } from '../api/types';

interface AuthContextValue {
  user: AuthUser | null;
  isLoading: boolean;
  login: (nrp: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null));

    (async () => {
      try {
        const session = await readSession();

        if (session) {
          // Tampilkan identitas tersimpan segera (UX responsif), lalu
          // pastikan token masih sah di latar belakang — interceptor 401
          // (setUnauthorizedHandler) akan mengeluarkan pengguna otomatis
          // kalau ternyata sudah dicabut/kedaluwarsa.
          setUser(session.user);
          apiClient.get('/user').catch(() => {});
        }
      } catch {
        // Sesi tersimpan tidak terbaca (mis. storage rusak/tidak
        // tersedia) — perlakukan sebagai belum login, JANGAN biarkan
        // layar loading macet permanen menunggu promise yang gagal.
      } finally {
        setIsLoading(false);
      }
    })();
  }, []);

  async function login(nrp: string, password: string): Promise<void> {
    const response = await apiClient.post<AuthResponse>('/auth/login', { nrp, password });
    await storeSession(response.data.token, response.data.user);
    setUser(response.data.user);
  }

  async function logout(): Promise<void> {
    try {
      await apiClient.post('/auth/logout');
    } finally {
      await clearSession();
      setUser(null);
    }
  }

  return <AuthContext.Provider value={{ user, isLoading, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth harus dipakai di dalam AuthProvider.');
  }

  return context;
}
