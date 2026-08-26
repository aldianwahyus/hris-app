<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ESS Mobile (TOR Fase I) — notifikasi pegawai (mis. ApprovalSlaReminder/
 * ApprovalSlaExpired). Infrastrukturnya SUDAH ada: notifikasi ditulis
 * lewat channel 'database' (lihat App\Notifications\ApprovalSlaReminder),
 * di sini hanya diekspos sebagai daftar untuk klien mobile.
 */
final class NotificationApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return response()->json([
            'data' => $user->notifications()->limit(30)->get(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $notification = $user->notifications()->where('id', $id)->first();

        abort_if($notification === null, 404);

        $notification->markAsRead();

        return response()->json(status: 204);
    }
}
