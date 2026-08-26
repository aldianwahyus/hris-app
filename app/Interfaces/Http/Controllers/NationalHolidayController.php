<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Kalender hari libur nasional — SYSADMIN/Admin HC. Tulis langsung
 * (BUKAN maker-checker): ini rujukan kalender kerja, bukan keputusan
 * bisnis SDM per pegawai — sama kategori dengan OfficeGeofenceController.
 */
final class NationalHolidayController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $holidays = DB::table('cfg_national_holidays')
            ->select('id', 'holiday_date', 'name', 'is_national', 'source_document')
            ->orderBy('holiday_date')
            ->get();

        return view('admin.national-holidays', compact('holidays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:150'],
            'is_national' => ['nullable', 'boolean'],
            'source_document' => ['nullable', 'string', 'max:150'],
        ]);

        $alreadyExists = DB::table('cfg_national_holidays')
            ->where('holiday_date', $validated['holiday_date'])
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyExists) {
            return back()->withInput()->with('gagal', 'Tanggal itu sudah terdaftar sebagai hari libur.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('cfg_national_holidays')->insert([
            'id' => $id,
            'holiday_date' => $validated['holiday_date'],
            'name' => $validated['name'],
            'is_national' => $validated['is_national'] ?? true,
            'source_document' => $validated['source_document'] ?? null,
            'created_by' => $this->actor->employeeId(),
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->forgetCacheForDate($validated['holiday_date']);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'national_holiday',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.holidays.index')->with('sukses', 'Hari libur tersimpan.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $holiday = DB::table('cfg_national_holidays')->where('id', $id)->first();

        abort_if($holiday === null, 404);

        DB::table('cfg_national_holidays')->where('id', $id)->update([
            'deleted_at' => new DateTimeImmutable,
        ]);

        $this->forgetCacheForDate($holiday->holiday_date);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'national_holiday',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['holiday_date' => $holiday->holiday_date, 'name' => $holiday->name],
        ));

        return redirect()->route('sysadmin.holidays.index')->with('sukses', 'Hari libur dihapus.');
    }

    private function forgetCacheForDate(string $date): void
    {
        $year = (int) (new DateTimeImmutable($date))->format('Y');
        Cache::forget("holiday:rows:{$year}");
    }

    private function currentAuditActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
