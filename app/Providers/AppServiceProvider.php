<?php

namespace App\Providers;

use App\Interfaces\Http\Support\ComputeNavigationBadgeCounts;
use App\Modules\Access\Contracts\CurrentActor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge notifikasi sidebar — dihitung SEKALI per muat halaman
        // untuk pengguna yang login, bukan per-tautan. Dibungkus
        // try/catch di sini JUGA (di luar try/catch internal
        // ComputeNavigationBadgeCounts) — layout TIDAK BOLEH pernah
        // gagal render gara-gara badge, halaman tanpa badge jauh lebih
        // baik daripada halaman 500.
        View::composer('layouts.app', function ($view) {
            $badgeCounts = [];
            $unreadNotificationCount = 0;
            $recentNotifications = collect();

            if (Auth::check()) {
                try {
                    $badgeCounts = app(ComputeNavigationBadgeCounts::class)->forActor(app(CurrentActor::class));
                } catch (Throwable) {
                    $badgeCounts = [];
                }

                // Lonceng notifikasi web — relasi Eloquent bawaan Notifiable
                // yang SAMA dipakai NotificationApiController mobile (lihat
                // App\Notifications\RequestDecided/ApprovalSlaReminder).
                // Dibungkus try/catch terpisah dengan alasan SAMA seperti
                // badge di atas: layout tidak boleh gagal render gara-gara ini.
                try {
                    $user = Auth::user();

                    if ($user !== null) {
                        $unreadNotificationCount = $user->unreadNotifications()->count();
                        $recentNotifications = $user->notifications()->limit(8)->get();
                    }
                } catch (Throwable) {
                    $unreadNotificationCount = 0;
                    $recentNotifications = collect();
                }
            }

            $view->with('badgeCounts', $badgeCounts);
            $view->with('unreadNotificationCount', $unreadNotificationCount);
            $view->with('recentNotifications', $recentNotifications);
        });
    }
}
