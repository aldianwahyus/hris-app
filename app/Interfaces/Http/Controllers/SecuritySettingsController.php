<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Sesi Aktif Saya" (Fase 2) — lingkup SELF murni, TIDAK butuh
 * permission (siapa pun yang login boleh lihat+cabut sesinya
 * SENDIRI di perangkat lain — pola "log out perangkat lain" umum).
 * BEDA dari SystemAdminSessionController: di sana admin bisa lihat/
 * cabut sesi SIAPA PUN, di sini HANYA milik sendiri, ditegakkan
 * lewat filter `user_id` di query, bukan sekadar UI.
 */
final class SecuritySettingsController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()?->id;
        abort_if($userId === null, 403);

        $currentSessionId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($row) use ($currentSessionId) {
                $row->last_activity_human = Carbon::createFromTimestamp($row->last_activity)->diffForHumans();
                $row->is_current = $row->id === $currentSessionId;

                return $row;
            });

        return view('ess.security-settings', ['sessions' => $sessions]);
    }

    public function revoke(Request $request, string $id): RedirectResponse
    {
        $userId = $request->user()?->id;
        abort_if($userId === null, 403);
        abort_if($id === $request->session()->getId(), 422, 'Tidak dapat mencabut sesi yang sedang Anda pakai — gunakan Keluar.');

        $deleted = DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        abort_if($deleted === 0, 404);

        return redirect()->route('security-settings.index')->with('sukses', 'Sesi berhasil dicabut.');
    }

    public function revokeOthers(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        abort_if($userId === null, 403);

        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return redirect()->route('security-settings.index')->with('sukses', 'Seluruh sesi di perangkat lain telah dicabut.');
    }
}
