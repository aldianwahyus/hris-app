<?php

namespace App\Providers;

use App\Interfaces\Http\Support\ComputeNavigationBadgeCounts;
use App\Modules\Access\Contracts\CurrentActor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $offboardingExitInterviewEligible = false;

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

                // Offboarding — modul baru (evaluasi PM/client 2026-09-02):
                // tautan "Wawancara Keluar" HANYA relevan bagi pegawai yang
                // pemisahannya sedang disetujui & belum mengisi — tanpa
                // pemeriksaan ini, tautan itu akan tampil PERMANEN di
                // navigasi utama SETIAP pegawai (hampir selalu mengarah ke
                // halaman 404 karena tidak ada pemisahan yang berlaku),
                // bukan hanya saat sungguh dibutuhkan.
                try {
                    $employeeId = Auth::user()?->employee_id;

                    if ($employeeId !== null) {
                        $offboardingExitInterviewEligible = DB::table('off_separation_requests as s')
                            ->where('s.employee_id', $employeeId)
                            ->where('s.status', 'approved')
                            ->whereNotExists(function ($q) {
                                $q->selectRaw('1')
                                    ->from('off_exit_interviews as i')
                                    ->whereColumn('i.separation_id', 's.id');
                            })
                            ->exists();
                    }
                } catch (Throwable) {
                    $offboardingExitInterviewEligible = false;
                }
            }

            $view->with('badgeCounts', $badgeCounts);
            $view->with('unreadNotificationCount', $unreadNotificationCount);
            $view->with('recentNotifications', $recentNotifications);
            $view->with('offboardingExitInterviewEligible', $offboardingExitInterviewEligible);
        });
    }
}
