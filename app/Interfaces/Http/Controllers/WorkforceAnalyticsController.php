<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\WorkforceAnalytics;
use Illuminate\View\View;

/**
 * Analitik Tenaga Kerja (Fase 2) — BERBASIS ATURAN TRANSPARAN, BUKAN
 * machine learning (lihat WorkforceAnalytics). Bank-wide saja, lingkup
 * SAMA HcDashboardController — permission workforce-analytics.view
 * (hr_approver + pemegang hc-dashboard.view lain).
 */
final class WorkforceAnalyticsController extends Controller
{
    public function __construct(private readonly WorkforceAnalytics $analytics) {}

    public function index(): View
    {
        return view('admin.workforce-analytics', [
            'headcountTrend' => $this->analytics->headcountTrend(),
            'turnoverRate' => $this->analytics->turnoverRate12Months(),
            'averageTenure' => $this->analytics->averageTenureYears(),
            'enpsTrend' => $this->analytics->enpsTrend(),
            'atRiskEmployees' => $this->analytics->atRiskEmployees(),
        ]);
    }
}
