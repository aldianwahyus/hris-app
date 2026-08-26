<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Tanpa berkas ini, paket Horizon TETAP menerapkan gerbang bawaannya
 * sendiri: melarang semua orang di luar 'local', TAPI mengizinkan
 * SIAPA PUN — termasuk yang belum masuk — begitu APP_ENV=local (default
 * paket, bukan celah aplikasi ini). Dasbor Horizon menampilkan payload
 * job (bisa memuat data pegawai lewat notifikasi SLA), jadi digerbangi
 * eksplisit ke Admin Sistem (Role::SystemAdmin) — dan sengaja
 * OVERRIDE authorization() penuh (bukan hanya gate()) agar aturan ini
 * berlaku bahkan di lokal, supaya proteksinya benar-benar dapat diuji
 * dengan akun demo, bukan lolos diam-diam lewat celah 'local'.
 */
final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user) => $user?->hasRole('system_admin') ?? false);
    }
}
