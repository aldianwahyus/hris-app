<?php

use App\Console\Commands\CheckExpiringContracts;
use App\Console\Commands\ProcessSlaReminders;
use App\Console\Commands\SyncAttendanceDevice;
use App\Interfaces\Http\Middleware\PreventBackHistoryCache;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum sebagai identity provider tunggal (DEC-02): permintaan
        // dari domain SPA yang terdaftar diautentikasi lewat sesi/cookie,
        // sisanya (mobile, dsb.) lewat token — satu guard, dua jalur masuk.
        $middleware->statefulApi();

        // CATATAN PRODUKSI: begitu aplikasi berjalan di belakang load
        // balancer/WAF sungguhan (ARCH-001 §3 — zonasi DMZ/App/Data),
        // WAJIB dikonfigurasi $middleware->trustProxies(at: [...IP LB...])
        // dengan IP eksplisit — BUKAN '*'. Tanpa itu $request->ip() salah
        // (melaporkan IP LB, bukan klien sesungguhnya) DAN mempercayai
        // '*' membuat header X-Forwarded-For dapat dipalsukan siapa pun,
        // mencemari ip_address pada aud_change_logs/aud_access_logs.
        // Sengaja TIDAK diatur di sini karena topologi LB sesungguhnya
        // belum diketahui — menebak salah lebih berbahaya daripada
        // membiarkan kosong.

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Seluruh rute web (login + halaman terautentikasi) TIDAK boleh
        // disimpan browser — data HRIS rahasia tidak boleh terlihat
        // lewat tombol "kembali" setelah logout. Lihat
        // PreventBackHistoryCache untuk alasan lengkapnya. Aman
        // diterapkan bank-lebar: tidak ada rute publik yang benar-benar
        // butuh caching di routes/web.php (satu-satunya rute guest
        // adalah /masuk, yang JUGA sebaiknya tidak di-cache).
        $middleware->web(append: [PreventBackHistoryCache::class]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Mitigasi RA-3 (ARCH-001 §10): keterlambatan persetujuan lembur
        // menghanguskan hak bayar pegawai. withoutOverlapping() mencegah
        // pengingat terkirim ganda bila proses sebelumnya belum selesai;
        // onOneServer() mencegah ganda pada topologi App ≥2 node (§3.4).
        $schedule->command(ProcessSlaReminders::class)
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->onOneServer();

        // Mesin fingerprint bersifat pasif — pindai menumpuk di tabel
        // singgah sampai diserap. 15 menit cukup dekat dengan waktu
        // nyata tanpa membebani mesin dengan permintaan terus-menerus.
        $schedule->command(SyncAttendanceDevice::class)
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // Manajemen Kontrak — pengingat kontrak kontrak/outsource akan
        // berakhir (evaluasi PM/client 2026-09-02).
        $schedule->command(CheckExpiringContracts::class)
            ->dailyAt('07:15')
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Jaring pengaman terakhir: exception yang lolos dari try/catch
        // lokal per controller TIDAK BOLEH tampil sebagai halaman 500
        // mentah (stack trace/Ignition) ke pengguna akhir — lihat audit
        // SEC-2026-08-ERR. Laravel sendiri sudah menangani baik untuk
        // ValidationException/AuthenticationException/HttpException
        // (404/403/419/429, lihat resources/views/errors/*.blade.php)
        // — closure ini SENGAJA mengembalikan null untuk itu semua agar
        // penanganan bawaan Laravel tetap jalan, tidak ditimpa.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            $referensi = Str::upper(Str::random(8));

            Log::error("[{$referensi}] ".$e::class.': '.$e->getMessage(), [
                'exception' => $e,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
            ]);

            // "Aman ditampilkan" HANYA exception domain/aturan bisnis
            // milik kode aplikasi sendiri (app/Modules atau app/Shared)
            // — bukan QueryException/PDOException (bisa membocorkan
            // struktur query/skema) atau exception lain dari vendor.
            // Pesannya SUDAH dirancang aman & informatif untuk pengguna
            // (lihat mis. ParameterNotFoundException, SppdTariffNotFound).
            $amanDitampilkan = ($e instanceof DomainException || $e instanceof RuntimeException || $e instanceof InvalidArgumentException)
                && ! ($e instanceof QueryException)
                && ! ($e instanceof PDOException)
                && str_contains($e->getFile(), DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR);

            $pesan = $amanDitampilkan
                ? $e->getMessage()
                : "Terjadi kesalahan pada sistem. Silakan coba lagi beberapa saat lagi. Jika berlanjut, laporkan ke Admin/IT dengan menyertakan kode referensi: {$referensi}.";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $pesan,
                    'reference' => $referensi,
                ], $amanDitampilkan ? 422 : 500);
            }

            if ($request->isMethod('GET')) {
                return response()->view('errors.500', [
                    'message' => $pesan,
                    'reference' => $amanDitampilkan ? null : $referensi,
                ], $amanDitampilkan ? 200 : 500);
            }

            return redirect()->back()->with('gagal', $pesan);
        });
    })->create();
