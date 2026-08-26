<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gamifikasi — ESS, TANPA middleware permission (pola sama
 * LmsEnrollmentController). Poin dari kelulusan kursus/asesmen
 * OTOMATIS (lihat AwardGamificationPoints); halaman ini hanya
 * menampilkan + biarkan pegawai ikut challenge yang tersedia.
 */
final class LmsGamificationController extends Controller
{
    public function leaderboard(): View
    {
        $rows = DB::table('lms_gamification_points as p')
            ->join('emp_employees as e', 'e.id', '=', 'p.employee_id')
            ->select('e.id', 'e.full_name', DB::raw('sum(p.points) as total_poin'))
            ->groupBy('e.id', 'e.full_name')
            ->orderByDesc('total_poin')
            ->limit(20)
            ->get();

        return view('lms.leaderboard', compact('rows'));
    }

    public function myBadges(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $totalPoints = (int) DB::table('lms_gamification_points')->where('employee_id', $user->employee_id)->sum('points');

        $myBadges = DB::table('lms_employee_badges as eb')
            ->join('lms_badges as b', 'b.id', '=', 'eb.badge_id')
            ->where('eb.employee_id', $user->employee_id)
            ->select('b.name', 'b.icon', 'b.description', 'eb.awarded_at')
            ->orderByDesc('eb.awarded_at')
            ->get();

        $today = now()->format('Y-m-d');
        $activeChallenges = DB::table('lms_challenges')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->orderBy('end_date')
            ->get();

        $myParticipation = DB::table('lms_challenge_participants')
            ->where('employee_id', $user->employee_id)
            ->pluck('status', 'challenge_id');

        return view('lms.my-badges', compact('totalPoints', 'myBadges', 'activeChallenges', 'myParticipation'));
    }

    public function joinChallenge(Request $request, string $challengeId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $challenge = DB::table('lms_challenges')->where('id', $challengeId)->where('is_active', true)->first();
        abort_if($challenge === null, 404);

        $alreadyJoined = DB::table('lms_challenge_participants')
            ->where('challenge_id', $challengeId)
            ->where('employee_id', $user->employee_id)
            ->exists();

        if ($alreadyJoined) {
            return back()->with('gagal', 'Anda sudah mengikuti challenge ini.');
        }

        $now = new DateTimeImmutable;

        DB::table('lms_challenge_participants')->insert([
            'id' => (string) Uuid7::generate(),
            'challenge_id' => $challengeId,
            'employee_id' => $user->employee_id,
            'status' => 'joined',
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return redirect()->route('lms.my-badges')->with('sukses', 'Anda bergabung dengan challenge ini.');
    }
}
