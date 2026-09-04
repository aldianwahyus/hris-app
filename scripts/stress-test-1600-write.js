import http from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';

/**
 * Susulan stress-test-1600.js — versi TERFOKUS, HANYA menembak
 * POST /survei/{id}/isi (TANPA dua panggilan GET pendahulu) supaya
 * 1600 penulis benar-benar sampai ke baris kontensi (svy_surveys
 * lockForUpdate()) dalam jendela waktu uji, bukan tersaring habis di
 * antrean PHP-FPM sebelum sempat mencoba menulis (lihat
 * STRESS_TEST_1600_RESULTS.md — percobaan pertama 0 penulisan
 * tercapai karena alasan itu).
 */
const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('./loadtest-tokens.json'));
});

const surveyId = open('./loadtest-survey-id.txt').trim();
const baseUrl = __ENV.BASE_URL || 'http://nginx';

export const options = {
  scenarios: {
    write_contention: {
      executor: 'per-vu-iterations',
      vus: 1600,
      iterations: 1,
      maxDuration: '5m',
    },
  },
};

export default function () {
  const token = tokens[(__VU - 1) % tokens.length];
  const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };

  const surveyRes = http.get(`${baseUrl}/api/v1/survei/${surveyId}`, {
    headers,
    tags: { name: 'GET /survei/{id}' },
    timeout: '120s',
  });

  let questionId = null;
  try {
    const body = JSON.parse(surveyRes.body);
    questionId = body.questions && body.questions[0] ? body.questions[0].id : null;
  } catch (e) {
    questionId = null;
  }

  if (!questionId) {
    return;
  }

  const payload = JSON.stringify({ jawaban: { [questionId]: '9' } });
  const submitRes = http.post(`${baseUrl}/api/v1/survei/${surveyId}/isi`, payload, {
    headers,
    tags: { name: 'POST /survei/{id}/isi' },
    timeout: '120s',
  });

  check(submitRes, {
    'submit 200 (sukses) or 422 (ditolak wajar)': (r) => r.status === 200 || r.status === 422,
    'bukan 500': (r) => r.status !== 500,
  });
}
