<?php

/**
 * Pembersihan setelah Uji Beban 1600 Pengguna — WAJIB dijalankan
 * setelah scripts/stress-test-1600*.js selesai. Menghapus SEMUA data
 * throwaway yang disemai stress-test-1600-seed.php (pegawai/pengguna/
 * token/survei berpola "loadtest"), TIDAK menyentuh data lain.
 *
 * Cara pakai (dari host, git bash):
 *   tail -n +2 scripts/stress-test-1600-cleanup.php | \
 *     docker compose exec -T app php artisan tinker
 */
$surveyId = trim(file_get_contents('/var/www/html/scripts/loadtest-survey-id.txt'));

$responseIds = DB::table('svy_responses')->where('survey_id', $surveyId)->pluck('id');
DB::table('svy_answers')->whereIn('response_id', $responseIds)->delete();
DB::table('svy_responses')->where('survey_id', $surveyId)->delete();
DB::table('svy_response_tokens')->where('survey_id', $surveyId)->delete();
DB::table('svy_questions')->where('survey_id', $surveyId)->delete();
DB::table('svy_surveys')->where('id', $surveyId)->delete();

echo 'survey cleanup done'."\n";

$userIds = DB::table('users')->where('email', 'like', 'loadtest.%@loadtest.local')->pluck('id');

foreach ($userIds->chunk(400) as $chunk) {
    DB::table('personal_access_tokens')->where('tokenable_type', 'App\Models\User')->whereIn('tokenable_id', $chunk)->delete();
    DB::table('model_has_roles')->where('model_type', 'App\Models\User')->whereIn('model_id', $chunk)->delete();
}

echo 'tokens+roles deleted'."\n";

DB::table('users')->where('email', 'like', 'loadtest.%@loadtest.local')->delete();
echo 'users deleted: remaining loadtest users = '.DB::table('users')->where('email', 'like', 'loadtest.%@loadtest.local')->count()."\n";

DB::table('emp_employees')->where('nrp', 'like', 'LOADTEST.%')->delete();
echo 'employees deleted: remaining loadtest employees = '.DB::table('emp_employees')->where('nrp', 'like', 'LOADTEST.%')->count()."\n";

echo 'total employees now: '.DB::table('emp_employees')->count()."\n";
echo 'total users now: '.DB::table('users')->count()."\n";

@unlink('/var/www/html/scripts/loadtest-tokens.json');
@unlink('/var/www/html/scripts/loadtest-survey-id.txt');

echo "CLEANUP DONE\n";
