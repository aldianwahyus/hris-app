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
 * Kontrol menu Aplikasi Mobile — SYSADMIN/Admin HC (permission
 * sysadmin-content.manage, sama dengan Kalender Hari Libur/Pola Shift/
 * dst.). Satu saklar per menu berlaku BANK-WIDE untuk SEMUA pengguna
 * mobile — TIDAK per peran (keputusan bisnis eksplisit, lihat migrasi
 * create_mobile_menu_items). Dibaca aplikasi mobile lewat
 * Api\V1\MobileMenuApiController — TANPA cache di sisi server, supaya
 * saklar yang baru diubah admin langsung terlihat saat aplikasi mobile
 * berikutnya memuat ulang daftar menu (setiap kali dibuka/kembali aktif).
 */
final class MobileMenuSettingsController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $items = DB::table('mobile_menu_items')->orderBy('label')->get();

        return view('admin.mobile-menu-settings', compact('items'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled_keys' => ['array'],
            'enabled_keys.*' => ['string'],
        ]);

        $enabledKeys = $validated['enabled_keys'] ?? [];
        $now = new DateTimeImmutable;
        $items = DB::table('mobile_menu_items')->get();
        $actor = $this->currentAuditActor($request);
        $anyChanged = false;

        foreach ($items as $item) {
            $shouldBeEnabled = in_array($item->key, $enabledKeys, true);

            if ($shouldBeEnabled === (bool) $item->is_enabled) {
                continue; // tidak berubah — jangan tulis baris/audit untuk sesuatu yang sama
            }

            DB::table('mobile_menu_items')->where('id', $item->id)->update([
                'is_enabled' => $shouldBeEnabled,
                'updated_by' => $this->actor->employeeId(),
                'updated_at' => $now,
                'version' => $item->version + 1,
            ]);

            $anyChanged = true;

            // auditable_id WAJIB uuid asli (aud_change_logs.auditable_id
            // UUID NOT NULL) — dicatat PER BARIS memakai id baris itu
            // sendiri, BUKAN satu entri gabungan dengan id semu seperti
            // "bank_wide" (bukan uuid, akan ditolak basis data).
            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'mobile_menu_item',
                auditableId: $item->id,
                action: AuditAction::Updated,
                oldValues: ['key' => $item->key, 'is_enabled' => (bool) $item->is_enabled],
                newValues: ['key' => $item->key, 'is_enabled' => $shouldBeEnabled],
            ));
        }

        if (! $anyChanged) {
            return redirect()->route('sysadmin.mobile-menu.index')->with('sukses', 'Tidak ada perubahan.');
        }

        return redirect()->route('sysadmin.mobile-menu.index')->with('sukses', 'Pengaturan menu Aplikasi Mobile tersimpan.');
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
