<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\SystemHealthCheck;
use Illuminate\View\View;

/** Dashboard Kesehatan Sistem (Fase 2) — lingkup TEKNIS, `role:system_admin` (lihat routes/web.php). */
final class SystemHealthController extends Controller
{
    public function __construct(private readonly SystemHealthCheck $health) {}

    public function index(): View
    {
        return view('admin.system-health', [
            'checks' => $this->health->run(),
            'recentErrors' => $this->health->recentErrors(),
        ]);
    }
}
