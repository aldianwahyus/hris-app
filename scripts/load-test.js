// Skrip Load Test — Fase 2 (evaluasi PM/client 2026-09-03).
//
// BUKAN pengganti perencanaan kapasitas produksi sesungguhnya — hanya
// mengukur lingkungan Docker dev di mesin ini, dengan data seed dev
// (bukan volume produksi). Lihat scripts/LOAD_TEST_RESULTS.md untuk
// cara menjalankan dan catatan keterbatasan lengkap.
//
// Cakupan SENGAJA dibatasi ke halaman PUBLIK (tanpa login): halaman
// /masuk melewati captcha WAJIB (mews/captcha, lihat LoginRequest)
// yang tidak bisa diselesaikan skrip otomatis tanpa membuka jalur
// pintas keamanan baru — menambah bypass captcha demi load test akan
// jadi risiko keamanan baru yang tidak sepadan manfaatnya di sini.
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    jelajah_publik: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 50 },
        { duration: '1m', target: 50 },
        { duration: '30s', target: 100 },
        { duration: '1m', target: 100 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1500'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

export default function () {
  const halamanMasuk = http.get(`${BASE_URL}/masuk`);
  check(halamanMasuk, { 'GET /masuk -> 200': (r) => r.status === 200 });

  const captcha = http.get(`${BASE_URL}/captcha-refresh`);
  check(captcha, { 'GET /captcha-refresh -> 200': (r) => r.status === 200 });

  const lowongan = http.get(`${BASE_URL}/lowongan`);
  check(lowongan, { 'GET /lowongan -> 200': (r) => r.status === 200 });

  sleep(1);
}
