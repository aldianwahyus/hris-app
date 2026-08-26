<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencegah browser menampilkan salinan tersimpan (cache lokal/bfcache)
 * dari halaman yang sudah diautentikasi setelah logout — session
 * server-side SUDAH benar dihancurkan (LogoutController::invalidate()
 * + regenerateToken()), tapi itu hanya menolak PERMINTAAN BARU.
 * Tombol "kembali" browser bisa menampilkan render HALAMAN LAMA dari
 * cache TANPA permintaan jaringan sama sekali bila server tidak
 * eksplisit melarangnya.
 *
 * Laravel/Symfony secara default hanya mengirim "Cache-Control:
 * no-cache, private" begitu sesi dimulai (Response::prepare()) —
 * cukup untuk shared cache/proxy, TAPI TIDAK cukup untuk bfcache
 * (back-forward cache) browser modern, yang secara eksplisit
 * memeriksa arahan "no-store" sebelum mau menyimpan halaman. Tanpa
 * "no-store", data HRIS rahasia (gaji, PPh21, data pribadi) tetap
 * terlihat lewat tombol kembali di komputer bersama/publik.
 *
 * CWE-525 (Information Exposure Through Browser Caching), OWASP
 * A01:2021 (Broken Access Control) / A04:2021 (Insecure Design).
 */
final class PreventBackHistoryCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0, private'
        );
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
