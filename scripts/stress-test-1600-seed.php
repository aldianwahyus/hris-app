<?php

/**
 * Uji Beban 1600 Pengguna (evaluasi PM/client 2026-09-04) — semai
 * 1600 pegawai+pengguna+token Sanctum THROWAWAY (NRP berpola
 * "LOADTEST.NNNNN", email "loadtest.NNNNN@loadtest.local") langsung
 * lewat basis data, TIDAK lewat endpoint /api/v1/auth/login — supaya
 * throttle:30,1 pada rute itu SAMA SEKALI tidak disentuh/dilemahkan
 * (pola sama actingAs() pada test PHPUnit: menyemai sesi yang sudah
 * valid, bukan mensimulasikan alur login sungguhan).
 *
 * Juga menyemai SATU survei eNPS aktif — target kontensi kunci baris
 * tunggal (svy_surveys, lihat SubmitSurveyResponse::handle()
 * lockForUpdate()) untuk stress-test-1600-write.js.
 *
 * Cara pakai (dari host, git bash):
 *   tail -n +2 scripts/stress-test-1600-seed.php | \
 *     docker compose exec -T app php artisan tinker
 *
 * (baris `<?php` dibuang saat dipipa — tinker REPL tidak menerimanya)
 *
 * WAJIB dijalankan HANYA di basis data dev — SELALU diikuti
 * scripts/stress-test-1600-cleanup.php setelah pengujian selesai.
 */
$officeId = DB::table('emp_employees')->value('office_id');
$positionId = DB::table('emp_employees')->value('position_id');
$pegawaiRoleId = DB::table('roles')->where('name', 'pegawai')->value('id');
$now = now();
$sharedHash = app('hash')->make('loadtest-unused-password');

$total = 1600;
$chunkSize = 400;

for ($start = 1; $start <= $total; $start += $chunkSize) {
    $end = min($start + $chunkSize - 1, $total);
    $employeeRows = [];

    for ($i = $start; $i <= $end; $i++) {
        $num = str_pad((string) $i, 5, '0', STR_PAD_LEFT);
        $employeeRows[] = [
            'id' => (string) Str::orderedUuid(),
            'nrp' => "LOADTEST.$num",
            'full_name' => "Pegawai Uji Beban $num",
            'join_date' => '2020-01-01',
            'employment_status' => 'tetap',
            'office_id' => $officeId,
            'position_id' => $positionId,
            'salary_step' => 1,
            'tanggungan' => 0,
            'tunjangan_jabatan_cents' => 0,
            'tunjangan_penyesuaian_cents' => 0,
            'email' => "loadtest.$num@loadtest.local",
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ];
    }

    DB::table('emp_employees')->insert($employeeRows);
}

echo 'employees inserted: '.DB::table('emp_employees')->where('nrp', 'like', 'LOADTEST.%')->count()."\n";

for ($start = 1; $start <= $total; $start += $chunkSize) {
    $end = min($start + $chunkSize - 1, $total);
    $employeeIds = DB::table('emp_employees')
        ->where('nrp', '>=', 'LOADTEST.'.str_pad((string) $start, 5, '0', STR_PAD_LEFT))
        ->where('nrp', '<=', 'LOADTEST.'.str_pad((string) $end, 5, '0', STR_PAD_LEFT))
        ->pluck('id', 'nrp');

    $userRows = [];

    foreach ($employeeIds as $nrp => $employeeId) {
        $num = substr($nrp, -5);
        $userRows[] = [
            'name' => "Pegawai Uji Beban $num",
            'email' => "loadtest.$num@loadtest.local",
            'password' => $sharedHash,
            'employee_id' => $employeeId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    DB::table('users')->insert($userRows);
}

echo 'users inserted: '.DB::table('users')->where('email', 'like', 'loadtest.%@loadtest.local')->count()."\n";

$userIds = DB::table('users')->where('email', 'like', 'loadtest.%@loadtest.local')->pluck('id');

foreach ($userIds->chunk($chunkSize) as $chunk) {
    $roleRows = $chunk->map(fn ($id) => [
        'role_id' => $pegawaiRoleId,
        'model_type' => 'App\Models\User',
        'model_id' => $id,
    ])->all();

    DB::table('model_has_roles')->insert($roleRows);
}

echo 'roles assigned: '.DB::table('model_has_roles')->where('role_id', $pegawaiRoleId)->count()."\n";

$tokens = [];

foreach ($userIds->chunk($chunkSize) as $chunk) {
    $tokenRows = [];
    $chunkTokens = [];

    foreach ($chunk as $userId) {
        $plainText = Str::random(40);
        $tokenRows[] = [
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => $userId,
            'name' => 'loadtest',
            'token' => hash('sha256', $plainText),
            'abilities' => json_encode(['*']),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $chunkTokens[$userId] = $plainText;
    }

    DB::table('personal_access_tokens')->insert($tokenRows);

    $inserted = DB::table('personal_access_tokens')
        ->whereIn('tokenable_id', array_keys($chunkTokens))
        ->where('name', 'loadtest')
        ->get(['id', 'tokenable_id']);

    foreach ($inserted as $row) {
        $tokens[] = $row->id.'|'.$chunkTokens[$row->tokenable_id];
    }
}

echo 'tokens created: '.count($tokens)."\n";

file_put_contents('/var/www/html/scripts/loadtest-tokens.json', json_encode($tokens));

echo 'tokens written to scripts/loadtest-tokens.json'."\n";

$surveyId = (string) Str::orderedUuid();
$questionId = (string) Str::orderedUuid();

DB::table('svy_surveys')->insert([
    'id' => $surveyId,
    'title' => 'Survei Uji Beban 1600 Pengguna',
    'type' => 'enps',
    'scope' => 'bank_wide',
    'is_anonymous' => false,
    'start_date' => now()->subDay()->toDateString(),
    'end_date' => now()->addDay()->toDateString(),
    'status' => 'aktif',
    'created_by' => DB::table('emp_employees')->where('nrp', 'SYSADMIN')->value('id'),
    'created_at' => $now,
    'updated_at' => $now,
    'version' => 1,
]);

DB::table('svy_questions')->insert([
    'id' => $questionId,
    'survey_id' => $surveyId,
    'question_text' => 'Seberapa besar kemungkinan Anda merekomendasikan Bank NTB Syariah?',
    'question_type' => 'nps_0_10',
    'display_order' => 1,
]);

file_put_contents('/var/www/html/scripts/loadtest-survey-id.txt', $surveyId);

echo "survey id: $surveyId\n";
echo "DONE\n";
