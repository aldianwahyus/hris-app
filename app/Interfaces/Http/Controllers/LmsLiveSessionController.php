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
 * Live Learning & Mentoring — ESS, TANPA middleware permission (semua
 * pegawai boleh mendaftar, pola sama LmsEnrollmentController).
 */
final class LmsLiveSessionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $sessions = DB::table('lms_live_sessions as s')
            ->leftJoin('lms_courses as c', 'c.id', '=', 's.course_id')
            ->leftJoin('emp_employees as f', 'f.id', '=', 's.facilitator_employee_id')
            ->where('s.is_active', true)
            ->select('s.*', 'c.title as course_title', 'f.full_name as facilitator_name')
            ->orderBy('s.scheduled_at')
            ->get();

        $myRegistrations = DB::table('lms_live_session_participants')
            ->where('employee_id', $user->employee_id)
            ->pluck('status', 'session_id');

        return view('lms.live-sessions-index', compact('sessions', 'myRegistrations'));
    }

    public function register(Request $request, string $sessionId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $session = DB::table('lms_live_sessions')->where('id', $sessionId)->where('is_active', true)->first();
        abort_if($session === null, 404);

        $alreadyRegistered = DB::table('lms_live_session_participants')
            ->where('session_id', $sessionId)
            ->where('employee_id', $user->employee_id)
            ->exists();

        if ($alreadyRegistered) {
            return back()->with('gagal', 'Anda sudah terdaftar pada sesi ini.');
        }

        $now = new DateTimeImmutable;

        DB::table('lms_live_session_participants')->insert([
            'id' => (string) Uuid7::generate(),
            'session_id' => $sessionId,
            'employee_id' => $user->employee_id,
            'status' => 'registered',
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return redirect()->route('lms.live-sessions.index')->with('sukses', 'Anda terdaftar pada sesi ini.');
    }
}
