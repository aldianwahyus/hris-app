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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Daftar Akun Jurnal — akun beban ("IA Beban Uang Lembur" dst) dan akun
 * penampungan pajak yang dipilih HC/Admin Cabang saat memproses batch
 * pembayaran lembur (lihat ProcessOvertimePaymentBatch). Tidak ada
 * konsep jurnal/COA di app ini sebelumnya — modul referensi baru,
 * dikelola HC/Admin Sistem (permission:sysadmin-content.manage yang
 * SUDAH ADA, pola sama Daftar Kantor/Jabatan). Tidak bisa dihapus,
 * hanya dinonaktifkan (is_active) — konsisten dengan seluruh master
 * data lain di app ini.
 */
final class JournalAccountController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function index(): View
    {
        $accounts = DB::table('fin_journal_accounts')->orderBy('category')->orderBy('name')->get();

        return view('admin.journal-accounts', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:beban,penampungan_pajak'],
        ]);

        $codeTaken = DB::table('fin_journal_accounts')->where('code', $validated['code'])->exists();

        if ($codeTaken) {
            return back()->withInput()->with('gagal', 'Kode akun itu sudah dipakai.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('fin_journal_accounts')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $this->currentAuditActor($request),
            auditableType: 'fin_journal_account',
            auditableId: $id,
            action: AuditAction::Created,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.journal-accounts.index')->with('sukses', 'Akun jurnal tersimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $account = DB::table('fin_journal_accounts')->where('id', $id)->first();
        abort_if($account === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:beban,penampungan_pajak'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('fin_journal_accounts')->where('id', $id)->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'is_active' => $validated['is_active'] ?? false,
            'updated_at' => new DateTimeImmutable,
            'version' => $account->version + 1,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'fin_journal_account',
            auditableId: $id,
            action: AuditAction::Updated,
            oldValues: (array) $account,
            newValues: $validated,
        ));

        return redirect()->route('sysadmin.journal-accounts.index')->with('sukses', 'Akun jurnal diperbarui.');
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
