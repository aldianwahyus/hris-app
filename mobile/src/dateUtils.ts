/**
 * 'YYYY-MM-DD' — bentuk yang diharapkan backend (mis. SubmitLeaveRequest,
 * SubmitSppdRequest). SENGAJA memakai komponen tanggal LOKAL
 * (getFullYear/getMonth/getDate), BUKAN toISOString(): date picker
 * (native maupun web) mengembalikan Date pada TENGAH MALAM WAKTU LOKAL
 * untuk tanggal yang dipilih — toISOString() mengonversinya ke UTC,
 * yang di seluruh Indonesia (WIB/WITA/WIT, semua UTC+7 ke atas) SELALU
 * mundur satu hari (mis. pilih 24 Agustus 00:00 WITA → UTC 23 Agustus
 * 16:00 → toISOString() menghasilkan "2026-08-23", SALAH). Bug ini
 * ditemukan lewat tinjauan kode — pengajuan cuti/lembur/SPPD sebelum
 * perbaikan ini selalu terkirim untuk tanggal SEHARI SEBELUM yang
 * benar-benar dipilih pengguna.
 */
export function toIsoDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

export function formatDateLong(date: Date): string {
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}
