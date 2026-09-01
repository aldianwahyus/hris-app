<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lonceng notifikasi web — cermin NotificationApiController mobile,
 * relasi Eloquent bawaan Notifiable yang SAMA
 * ($user->notifications()/unreadNotifications()), hanya beda bentuk
 * respons (redirect+tandai dibaca sekaligus, bukan JSON terpisah —
 * cocok untuk pola klik-langsung-dari-dropdown pada web).
 */
final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $notifications = $user->notifications()->paginate(30);

        return view('admin.notifications', compact('notifications'));
    }

    /** Menandai satu notifikasi dibaca, lalu kembali ke halaman sebelumnya — dipakai dari dropdown maupun daftar lengkap. */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification !== null) {
            $notification->markAsRead();
        }

        return redirect()->back();
    }
}
