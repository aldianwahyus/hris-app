import { BadgeTone } from './components/Badge';

/**
 * Status pengajuan cuti/lembur/SPPD (dua tahap: Atasan Langsung lalu
 * Pimpinan Kantor — lihat ApprovalQueueController di backend).
 */
export const REQUEST_STATUS: Record<string, { label: string; tone: BadgeTone }> = {
  pending: { label: 'Menunggu Atasan Langsung', tone: 'warning' },
  pending_pimpinan: { label: 'Menunggu Pimpinan Kantor', tone: 'warning' },
  approved: { label: 'Disetujui', tone: 'success' },
  rejected: { label: 'Ditolak', tone: 'danger' },
  cancelled: { label: 'Dibatalkan', tone: 'neutral' },
  expired: { label: 'Kedaluwarsa', tone: 'danger' },
  disbursed: { label: 'Sudah Dicairkan', tone: 'success' },
};

export function requestStatus(status: string): { label: string; tone: BadgeTone } {
  return REQUEST_STATUS[status] ?? { label: status, tone: 'neutral' };
}

export const ATTENDANCE_STATUS: Record<string, { label: string; tone: BadgeTone }> = {
  hadir: { label: 'Hadir', tone: 'success' },
  telat: { label: 'Terlambat', tone: 'warning' },
  absen: { label: 'Tidak Hadir', tone: 'danger' },
};

export function attendanceStatus(status: string): { label: string; tone: BadgeTone } {
  return ATTENDANCE_STATUS[status] ?? { label: status, tone: 'neutral' };
}
