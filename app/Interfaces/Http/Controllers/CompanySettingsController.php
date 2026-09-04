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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Pengaturan Perusahaan (Fase 2) — nama+lambang dinamis yang tampil di
 * SEMUA dokumen resmi cetak (Memo Internal, Nota Debet, Jurnal Slip,
 * dst., lihat CompanyProfile) — SATU tempat, TIDAK PERNAH butuh
 * perubahan kode saat identitas perusahaan berubah (rebranding,
 * merger, dst.). Permission SAMA Kalender Hari Libur/Pola Shift/Menu
 * Aplikasi Mobile (sysadmin-content.manage) — pola "pengaturan konten
 * sistem" yang sudah mapan, bukan permission baru.
 */
final class CompanySettingsController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $settings = $this->currentRow();

        return view('admin.company-settings', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $this->currentRow();

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ], [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'logo.image' => 'Lambang harus berupa berkas gambar.',
            'logo.mimes' => 'Lambang hanya boleh berformat PNG, JPG, atau SVG.',
            'logo.max' => 'Ukuran lambang maksimal 2 MB.',
        ]);

        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $stored = $request->file('logo')->store('perusahaan', 's3');
            abort_if($stored === false, 500, 'Gagal mengunggah lambang — coba lagi.');
            $newLogoPath = $stored;
        }

        $now = new DateTimeImmutable;
        $oldValues = ['company_name' => $settings->company_name, 'logo_path' => $settings->logo_path];

        DB::table('company_settings')->where('id', $settings->id)->update([
            'company_name' => $validated['company_name'],
            'logo_path' => $newLogoPath ?? $settings->logo_path,
            'updated_by' => $this->actor->employeeId(),
            'updated_at' => $now,
            'version' => $settings->version + 1,
        ]);

        // Lambang LAMA dihapus SETELAH baris baru tersimpan — pola SAMA
        // EmployeeCvController::updatePhoto() — supaya kalau penyimpanan
        // di atas gagal, lambang lama yang masih dipakai tidak ikut hilang.
        if ($newLogoPath !== null && $settings->logo_path !== null) {
            Storage::disk('s3')->delete($settings->logo_path);
        }

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'company_settings',
            auditableId: $settings->id,
            action: AuditAction::Updated,
            oldValues: $oldValues,
            newValues: ['company_name' => $validated['company_name'], 'logo_path' => $newLogoPath ?? $settings->logo_path],
        ));

        return redirect()->route('sysadmin.company-settings.index')->with('sukses', 'Pengaturan perusahaan tersimpan.');
    }

    /** @return object{id: string, company_name: string, logo_path: ?string, version: int} */
    private function currentRow(): object
    {
        $row = DB::table('company_settings')->orderBy('created_at')->first();

        abort_if($row === null, 500, 'Pengaturan perusahaan belum diinisialisasi.');

        /** @var object{id: string, company_name: string, logo_path: ?string, version: int} $row */
        return $row;
    }
}
