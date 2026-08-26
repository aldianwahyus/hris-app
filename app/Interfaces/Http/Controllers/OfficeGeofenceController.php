<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Titik ordinat (geofence) tiap kantor — lingkup TEKNIS Admin Sistem,
 * sama seperti Konfigurasi Parameter Sistem/Skala Imbalan Kerja.
 * Tulis langsung (BUKAN maker-checker): ini fakta fisik kantor
 * (koordinat, radius), bukan keputusan bisnis SDM yang butuh
 * persetujuan berjenjang. latitude/longitude/geofence_radius_m dipakai
 * langsung oleh GeofencePolicy (absensi GPS ESS).
 */
final class OfficeGeofenceController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $offices = DB::table('md_offices')
            ->select('id', 'code', 'name', 'office_type', 'latitude', 'longitude', 'geofence_radius_m')
            ->orderBy('office_type')
            ->orderBy('name')
            ->get();

        return view('admin.office-geofence', compact('offices'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['required', 'integer', 'min:1'],
        ]);

        $office = DB::table('md_offices')->where('id', $id)->first();

        abort_if($office === null, 404);

        DB::table('md_offices')->where('id', $id)->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'geofence_radius_m' => $validated['geofence_radius_m'],
            'updated_at' => new DateTimeImmutable,
            'version' => $office->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'md_office',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: [
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'geofence_radius_m' => $office->geofence_radius_m,
            ],
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.office-geofence.index')
            ->with('sukses', "Titik ordinat {$office->name} tersimpan.");
    }
}
