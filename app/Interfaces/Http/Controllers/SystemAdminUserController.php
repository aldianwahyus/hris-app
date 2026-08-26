<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Access\Domain\SegregationOfDutyPolicy;
use App\Modules\Access\Domain\SegregationOfDutyViolation;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Manajemen akun login untuk Admin Sistem (IT) — lingkup TEKNIS, bukan
 * proses bisnis SDM (lihat Role::SystemAdmin). Reset kata sandi dan
 * penugasan peran; tidak pernah mengubah data cuti/lembur/data induk
 * pegawai.
 *
 * Penugasan peran BUKAN "Super Admin" lewat pintu belakang: aktor
 * TIDAK PERNAH boleh mengubah perannya sendiri (mencegah eskalasi hak
 * mandiri), dan setiap perubahan tercatat penuh di audit trail —
 * Auditor (lingkup BANK_WIDE, independen, §6.3) dapat meninjaunya
 * kapan pun lewat Log Audit.
 *
 * Kata sandi baru sengaja HANYA ditampilkan sekali lewat flash session
 * (tidak pernah disimpan dalam bentuk terbaca) — pola standar reset
 * kata sandi oleh admin.
 */
final class SystemAdminUserController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $auditRepository,
    ) {}

    public function index(): View
    {
        $users = User::query()
            ->with(['employee', 'roles'])
            ->orderBy('name')
            ->get();

        $availableRoles = Role::cases();

        return view('admin.users', compact('users', 'availableRoles'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $newPassword = Str::random(14);

        // Reset kata sandi TANPA mencabut sesi/token yang sudah terbit
        // hanya setengah pekerjaan — skenario utama fitur ini adalah
        // insiden (akun diduga bocor), sehingga sesi web aktif (tabel
        // sessions, SESSION_DRIVER=database), token "ingat saya", dan
        // seluruh token Sanctum (klien mobile, DEC-02) WAJIB ikut
        // dicabut. Tanpa ini, penyerang yang sudah punya sesi/cookie
        // "ingat saya"/token mobile tetap bisa masuk ulang tanpa kata
        // sandi baru sama sekali.
        $user->forceFill(['password' => Hash::make($newPassword)])->save();
        $user->setRememberToken(Str::random(60));
        $user->save();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->recordAccountAudit($request, $user, AuditAction::PasswordReset);

        return redirect()
            ->route('sysadmin.users.index')
            ->with('kata_sandi_baru', $newPassword)
            ->with('kata_sandi_untuk', $user->name);
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $request->validate(['role' => ['required', new Enum(Role::class)]]);

        $this->guardNotSelf($user);

        $role = Role::from($request->string('role')->toString());

        if ($user->hasRole($role->value)) {
            return redirect()->route('sysadmin.users.index')
                ->with('gagal', "{$user->name} sudah memiliki peran {$role->label()}.");
        }

        // SEC-2026-08: sebelumnya TIDAK ADA — celah ini memungkinkan
        // satu akun diberi hr_admin (maker) DAN hr_approver (checker)
        // sekaligus, atau auditor dirangkap dengan peran operasional,
        // melumpuhkan model maker-checker/independensi Auditor (§6.3)
        // yang ditegakkan di seluruh modul lain.
        $currentRoles = array_map(
            static fn (string $name): Role => Role::from($name),
            $user->getRoleNames()->all(),
        );

        if (! (new SegregationOfDutyPolicy)->canAssign($currentRoles, $role)) {
            $violation = SegregationOfDutyViolation::forRole($role);

            return redirect()->route('sysadmin.users.index')->with('gagal', $violation->getMessage());
        }

        $user->assignRole($role->value);

        $this->recordAccountAudit($request, $user, AuditAction::RoleGranted, newValues: ['role' => $role->value]);

        return redirect()->route('sysadmin.users.index')
            ->with('sukses', "Peran {$role->label()} diberikan kepada {$user->name}.");
    }

    public function revokeRole(Request $request, User $user, string $role): RedirectResponse
    {
        $roleEnum = Role::tryFrom($role);
        abort_if($roleEnum === null, 404, 'Peran tidak dikenal.');

        $this->guardNotSelf($user);

        $user->removeRole($role);

        $this->recordAccountAudit($request, $user, AuditAction::RoleRevoked, oldValues: ['role' => $role]);

        return redirect()->route('sysadmin.users.index')
            ->with('sukses', "Peran {$roleEnum->label()} dicabut dari {$user->name}.");
    }

    /** Mencegah Admin Sistem mengubah perannya SENDIRI — bukan sekadar tata krama UI, ini pagar anti eskalasi hak mandiri. */
    private function guardNotSelf(User $user): void
    {
        abort_if(
            $user->employee_id !== null && $user->employee_id === $this->actor->employeeId(),
            403,
            'Tidak dapat mengubah peran akun Anda sendiri — minta Admin Sistem lain melakukannya.',
        );
    }

    /**
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $oldValues
     */
    private function recordAccountAudit(
        Request $request,
        User $user,
        AuditAction $action,
        ?array $newValues = null,
        ?array $oldValues = null,
    ): void {
        abort_if($user->employee_id === null, 500, 'Akun ini tidak tertaut ke data pegawai — tidak dapat dicatat pada audit trail.');

        $this->auditRepository->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            auditableType: 'user_account',
            auditableId: $user->employee_id,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
        ));
    }
}
