/**
 * Bentuk JSON di sini dicocokkan langsung dari kode controller backend
 * (bukan tebakan) — lihat app/Modules/{Modul}/Interfaces/Http/Controllers/Api/V1
 * dan app/Modules/Access/Interfaces/Http/Controllers/Api/V1/TokenController.php.
 */

export interface AuthUser {
  id: number;
  nrp: string | null;
  nama: string | null;
  roles: string[];
}

export interface AuthResponse {
  token: string;
  user: AuthUser;
}

export interface ListResponse<T> {
  data: T[];
}

// Kolom tabel ditampilkan apa adanya (snake_case, sesuai respons backend).
// Hanya field yang benar-benar dipakai di UI yang didefinisikan ketat;
// sisanya lewat index signature agar tidak perlu disinkronkan tiap ada
// kolom baru di tabel yang belum ditampilkan mobile.
interface RequestRow {
  id: string;
  status: string;
  created_at: string;
  [key: string]: unknown;
}

export interface LeaveRequestRow extends RequestRow {
  request_number: string;
  start_date: string;
  end_date: string;
  reason: string | null;
  decision_note: string | null;
}

// sisa_cuti = jumlah kantong tahun berjalan + bawaan tahun lalu (SAMA
// logika LeaveApiController::remainingBalance() backend, LeaveBucket::
// remaining() per kantong) — TIDAK termasuk kantong tahun-tahun lain
// yang sudah kedaluwarsa, konsisten dengan basis perhitungan saat
// mengajukan cuti.
export interface LeaveListResponse extends ListResponse<LeaveRequestRow> {
  sisa_cuti: number;
}

export interface OvertimeRequestRow extends RequestRow {
  spkl_number: string;
  work_date: string;
  overtime_type: string;
  amount_cents: number | null;
  decision_note: string | null;
}

export interface SppdRequestRow extends RequestRow {
  request_number: string;
  destination: string;
  start_date: string;
  end_date: string;
  decision_note: string | null;
}

// Izin Tidak Masuk Bekerja — TERPISAH dari Cuti (tidak memotong saldo
// cuti tahunan sama sekali), 1 tahap (Atasan Langsung), lihat
// IzinApiController/SubmitIzinRequest.
export interface IzinRequestRow extends RequestRow {
  request_number: string;
  category: string;
  start_date: string;
  end_date: string;
  total_days: string;
  reason: string;
  attachment_path: string | null;
  attachment_original_name: string | null;
  decision_note: string | null;
}

// Istirahat/Kembali OPSIONAL (boleh langsung Masuk→Pulang) — TAPI begitu
// break_start_at terisi, Pulang diblokir server sampai break_end_at juga
// terisi, lihat RecordGpsAttendance.
export interface AttendanceRecordRow {
  id: string;
  work_date: string;
  status: string;
  check_in_at: string | null;
  check_in_source: string | null;
  break_start_at: string | null;
  break_end_at: string | null;
  check_out_at: string | null;
  check_out_source: string | null;
  [key: string]: unknown;
}

export type AttendanceAction = 'masuk' | 'istirahat' | 'kembali' | 'pulang';

export interface AttendanceSubmitResponse {
  action: AttendanceAction;
  // Hanya terisi untuk action 'masuk' (hadir/telat ditentukan saat
  // masuk) — null untuk istirahat/kembali/pulang.
  status: string | null;
}

interface PayslipLineItem {
  deduction_type?: string;
  addition_type?: string;
  amount_cents: number;
  note: string | null;
}

export interface PayslipRow {
  id: string;
  period: string;
  // take_home_partial_cents TIDAK PERNAH memutasi lewat potongan/
  // tambahan ad-hoc (lihat komentar PayslipApiController) — JANGAN
  // dipakai untuk tampilan THP, itu sebabnya "partial". take_home_cents
  // SUDAH memperhitungkan deductions/additions di bawah, PERSIS sama
  // dengan angka pada PDF/halaman web — pakai field INI untuk tampilan.
  take_home_partial_cents: number;
  take_home_cents: number;
  deductions: PayslipLineItem[];
  additions: PayslipLineItem[];
  // Komponen LAIN yang belum terhitung (mis. PPh21 belum final) — tidak
  // ada hubungannya dengan deductions/additions ad-hoc di atas.
  // PayslipApiController memakai DB::table() (query builder), BUKAN
  // model Eloquent — kolom json TIDAK otomatis di-decode seperti kolom
  // 'data' pada NotificationRow (yang lewat relasi Eloquent). Ini
  // string JSON mentah, harus di-parse manual, lihat PayslipScreen.
  pending_components: string;
  [key: string]: unknown;
}

export interface NotificationRow {
  id: string;
  type: string;
  data: {
    message?: string;
    request_number?: string;
    document_type?: string;
    [key: string]: unknown;
  };
  read_at: string | null;
  created_at: string;
}

export interface NotificationListResponse {
  data: NotificationRow[];
  unread_count: number;
}

// Aset Saya (Fase 2) — BACA SAJA, cermin AssetAssignmentController::mine().
export interface AssetAssignmentRow {
  asset_code: string;
  name: string;
  category: string;
  brand_model: string | null;
  serial_number: string | null;
  assigned_at: string;
}

// Layanan Dokumen Mandiri (Fase 2) — status: pending | diproses | siap | ditolak.
// TIDAK ada unduh PDF di mobile (pola SAMA Slip Gaji, lihat PayslipScreen) —
// dokumen yang 'siap' diunduh lewat aplikasi web.
export interface DocumentRequestRow {
  id: string;
  document_type: string;
  purpose: string;
  status: string;
  decision_note: string | null;
  created_at: string;
  [key: string]: unknown;
}

// HR Helpdesk (Fase 2) — status: terbuka | diproses | selesai | ditutup.
export interface HelpdeskTicketRow {
  id: string;
  ticket_number: string;
  category: string;
  subject: string;
  description: string;
  status: string;
  priority: string;
  assigned_to: string | null;
  created_at: string;
  [key: string]: unknown;
}

export interface HelpdeskReplyRow {
  message: string;
  created_at: string;
  author_employee_id: string;
  author_name: string;
}

export interface HelpdeskDetailResponse {
  data: HelpdeskTicketRow;
  replies: HelpdeskReplyRow[];
}

// Survei Keterlibatan (Fase 2) — eNPS/Pulse/Kustom.
export interface SurveyRow {
  id: string;
  title: string;
  description: string | null;
  type: string;
  is_anonymous: boolean;
  start_date: string;
  end_date: string;
  [key: string]: unknown;
}

export interface SurveyListResponse {
  data: SurveyRow[];
  responded_ids: string[];
}

export interface SurveyQuestionRow {
  id: string;
  question_text: string;
  question_type: 'nps_0_10' | 'rating_1_5' | 'pilihan_ganda' | 'teks';
  options: string[];
  display_order: number;
}

export interface SurveyDetailResponse {
  data: SurveyRow;
  questions: SurveyQuestionRow[];
}

// Menu Aplikasi Mobile yang boleh tampil — dikendalikan SYSADMIN/Admin HC
// lewat halaman web (bank-wide, bukan per peran). Kunci di sini WAJIB
// cocok persis dengan mobile_menu_items.key (lihat migrasi
// create_mobile_menu_items) — 'absensi' | 'cuti' | 'lembur' | 'sppd' |
// 'izin' | 'slip_gaji' | 'notifikasi'. Index signature (bukan enum
// tertutup) SENGAJA: menu baru yang ditambahkan backend nanti tidak
// memutus tipe di sini, dan MobileMenuContext memperlakukan kunci yang
// tidak dikenal sebagai TETAP TAMPIL (bawaan aman).
export interface MobileMenuConfigResponse {
  data: Record<string, boolean>;
}
