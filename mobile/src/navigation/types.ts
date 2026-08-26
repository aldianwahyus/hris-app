export type AuthStackParamList = {
  Login: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Absensi: undefined;
  Cuti: undefined;
  Lembur: undefined;
  Lainnya: undefined;
};

/** Layar level-atas — dipush DI ATAS tab bar (Sppd/SlipGaji/Notifikasi tidak
 *  cukup sering dipakai untuk jadi tab sendiri, dijangkau lewat menu Lainnya). */
export type MainStackParamList = {
  Tabs: undefined;
  Sppd: undefined;
  SlipGaji: undefined;
  Notifikasi: undefined;
  Izin: undefined;
};
