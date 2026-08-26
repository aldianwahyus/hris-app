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
}

export interface OvertimeRequestRow extends RequestRow {
  spkl_number: string;
  work_date: string;
  overtime_type: string;
  amount_cents: number | null;
}

export interface SppdRequestRow extends RequestRow {
  request_number: string;
  destination: string;
  start_date: string;
  end_date: string;
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

export interface PayslipRow {
  id: string;
  period: string;
  // Take-home SEBAGIAN — komponen di pending_components (mis. PPh21,
  // Tunjangan Kinerja/Kemahalan) belum terhitung saat slip ini dibuat.
  // Bukan nominal bersih final; lihat pay_payslips.pending_components.
  take_home_partial_cents: number;
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
