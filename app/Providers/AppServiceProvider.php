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

            if (Auth::check()) {
                try {
                    $badgeCounts = app(ComputeNavigationBadgeCounts::class)->forActor(app(CurrentActor::class));
                } catch (Throwable) {
                    $badgeCounts = [];
                }
            }

            $view->with('badgeCounts', $badgeCounts);
        });
    }
}
