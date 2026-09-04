import React, { createContext, useContext, useEffect, useRef, useState } from 'react';
import { AppState, AppStateStatus } from 'react-native';
import { apiClient } from '../api/client';
import { MobileMenuConfigResponse } from '../api/types';
import { useAuth } from './AuthContext';

export type MobileMenuKey =
  | 'absensi'
  | 'cuti'
  | 'lembur'
  | 'sppd'
  | 'izin'
  | 'slip_gaji'
  | 'notifikasi'
  | 'aset'
  | 'dokumen'
  | 'helpdesk'
  | 'survei';

interface MobileMenuContextValue {
  isEnabled: (key: MobileMenuKey) => boolean;
}

const MobileMenuContext = createContext<MobileMenuContextValue | null>(null);

/**
 * Daftar menu yang boleh tampil — dikendalikan SYSADMIN/Admin HC lewat
 * halaman web (bank-wide, satu saklar untuk SEMUA pengguna mobile).
 * Diambil ulang setiap kali aplikasi DIBUKA (mount) MAUPUN KEMBALI AKTIF
 * (AppState 'active', mis. pengguna kembali dari background) — supaya
 * saklar yang baru diubah admin langsung terlihat tanpa perlu logout/
 * update aplikasi, sesuai keputusan bisnis eksplisit.
 *
 * Bawaan AMAN: sebelum permintaan pertama selesai, saat permintaan
 * gagal (mis. jaringan terputus), ATAU untuk kunci yang tidak dikenal/
 * belum disemai backend — menu dianggap TETAP TAMPIL (fail-open).
 * Fitur ini hanya boleh MENYEMBUNYIKAN menu atas perintah eksplisit
 * admin, tidak pernah menyembunyikannya diam-diam akibat gangguan
 * jaringan.
 */
export function MobileMenuProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const [config, setConfig] = useState<Record<string, boolean>>({});
  const appState = useRef<AppStateStatus>(AppState.currentState);

  useEffect(() => {
    if (!user) {
      return;
    }

    const fetchConfig = () => {
      apiClient
        .get<MobileMenuConfigResponse>('/menu-mobile')
        .then((response) => setConfig(response.data.data))
        .catch(() => {
          // Gagal diam-diam — bawaan fail-open (lihat docblock di atas)
          // sudah menampilkan semua menu, tidak perlu penanganan lanjut.
        });
    };

    fetchConfig();

    const subscription = AppState.addEventListener('change', (nextState) => {
      if (appState.current.match(/inactive|background/) && nextState === 'active') {
        fetchConfig();
      }

      appState.current = nextState;
    });

    return () => subscription.remove();
  }, [user]);

  function isEnabled(key: MobileMenuKey): boolean {
    return config[key] !== false;
  }

  return <MobileMenuContext.Provider value={{ isEnabled }}>{children}</MobileMenuContext.Provider>;
}

export function useMobileMenu(): MobileMenuContextValue {
  const context = useContext(MobileMenuContext);

  if (!context) {
    throw new Error('useMobileMenu harus dipakai di dalam MobileMenuProvider.');
  }

  return context;
}
