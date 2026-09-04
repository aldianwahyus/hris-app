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
  AsetSaya: undefined;
  AjukanDokumen: undefined;
  TiketBantuan: undefined;
  TiketBantuanDetail: { id: string };
  Survei: undefined;
  SurveiIsi: { id: string };
};

/**
 * Subset dari MainStackParamList yang TIDAK butuh params — dipakai
 * tempat layar dituju lewat variabel generik (mis. QUICK_ACTIONS di
 * HomeScreen, MENU di LainnyaScreen), supaya navigate() tetap type-safe
 * tanpa memaksa SETIAP pemanggil generik menyediakan params untuk layar
 * yang butuh (TiketBantuanDetail/SurveiIsi HANYA dituju dengan params
 * eksplisit dari layar sumbernya masing-masing, bukan lewat daftar ini).
 */
export type ParamlessMainStackRoute = {
  [K in keyof MainStackParamList]: MainStackParamList[K] extends undefined ? K : never;
}[keyof MainStackParamList];
