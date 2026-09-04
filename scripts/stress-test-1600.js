import http from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';
import { Counter } from 'k6/metrics';

/**
 * Uji beban 1600 pengguna serentak (evaluasi PM/client 2026-09-04) —
 * BEDA dari scripts/load-test.js (halaman publik, tanpa login): skrip
 * ini menembak endpoint API mobile TERAUTENTIKASI dengan token yang
 * SUDAH disemai lewat scripts/stress-test-1600-seed.php (dibuat di
 * luar jalur login — TIDAK melewati/melemahkan throttle:30,1 pada
 * /api/v1/auth/login sama sekali, murni menyemai sesi yang sudah
 * valid, pola sama actingAs() pada test PHPUnit).
 *
 * Alur REALISTIS per pengguna: GET /user → GET /notifikasi →
 * GET /survei/{id} → POST /survei/{id}/isi — mengukur pengalaman
 * pengguna ujung-ke-ujung. Untuk pengujian kontensi TULIS terfokus
 * (lebih relevan untuk pertanyaan "apakah terjadi deadlock"), lihat
 * stress-test-1600-write.js.
 *
 * Lihat scripts/STRESS_TEST_1600_RESULTS.md untuk hasil+keterbatasan.
 */
const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('./loadtest-tokens.json'));
});

const surveyId = open('./loadtest-survey-id.txt').trim();

const baseUrl = __ENV.BASE_URL || 'http://nginx';

export const options = {
  scenarios: {
    stress_1600: {
      executor: 'per-vu-iterations',
      vus: 1600,
      iterations: 1,
      maxDuration: '5m',
      startTime: '0s',
    },
  },
  thresholds: {
    // Sengaja TANPA ambang gagal-otomatis (thresholds abort) — tujuan
    // uji ini MENGUKUR apa yang terjadi pada 1600 pengguna, bukan
    // menilai lulus/gagal sepihak lewat threshold.
  },
};

const deadlockCounter = new Counter('postgres_deadlock_responses');
const lockTimeoutCounter = new Counter('lock_timeout_responses');

export default function () {
  const token = tokens[(__VU - 1) % tokens.length];
  const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };

  const userRes = http.get(`${baseUrl}/api/v1/user`, { headers, tags: { name: 'GET /user' } });
  check(userRes, { 'GET /user 200': (r) => r.status === 200 });

  const notifRes = http.get(`${baseUrl}/api/v1/notifikasi`, { headers, tags: { name: 'GET /notifikasi' } });
  check(notifRes, { 'GET /notifikasi 200': (r) => r.status === 200 });

  const questionId = (() => {
    try {
      const surveyRes = http.get(`${baseUrl}/api/v1/survei/${surveyId}`, { headers, tags: { name: 'GET /survei/{id}' } });
      const body = JSON.parse(surveyRes.body);
      return body.questions && body.questions[0] ? body.questions[0].id : null;
    } catch (e) {
      return null;
    }
  })();

  if (questionId) {
    const payload = JSON.stringify({ jawaban: { [questionId]: '9' } });
    const submitRes = http.post(`${baseUrl}/api/v1/survei/${surveyId}/isi`, payload, {
      headers,
      tags: { name: 'POST /survei/{id}/isi' },
    });

    check(submitRes, { 'POST /survei/isi 200 or 422': (r) => r.status === 200 || r.status === 422 });

    if (submitRes.status === 500 && /deadlock/i.test(submitRes.body)) {
      deadlockCounter.add(1);
    }

    if (submitRes.status === 500 && /lock timeout|could not obtain lock/i.test(submitRes.body)) {
      lockTimeoutCounter.add(1);
    }
  }
}
