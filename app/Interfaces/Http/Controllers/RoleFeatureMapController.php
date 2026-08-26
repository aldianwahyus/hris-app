<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\PermissionCatalog;
use App\Modules\Access\Domain\Role;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peta peran → izin — HALAMAN INI SEKARANG MENULIS `role_has_permissions`
 * (bukan sekadar rujukan visual seperti `RoleFeatureMap` lama). Keputusan
 * akses sesungguhnya 100% di `role_has_permissions`, dibaca langsung oleh
 * middleware `permission:xxx` di routes/web.php — halaman ini adalah UI
 * atas tabel itu.
 *
 * `sysadmin.role-map.*` sendiri SENGAJA tetap digerbangi `role:xxx`
 * hardcode (bukan `permission:xxx`) — ini "kunci gerbang": kalau
 * kemampuan mengelola akses ITU SENDIRI didinamisasi lewat sistem yang
 * sama, satu kesalahan centang bisa mengunci semua admin dari halaman
 * ini tanpa jalan keluar selain akses database langsung.
 */
final class RoleFeatureMapController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $auditRepository,
    ) {}

    public function index(): View
    {
        $roles = Role::cases();
        $roleIds = DB::table('roles')->pluck('id', 'name');

        $grantedByRole = [];

        foreach ($roles as $role) {
            $roleId = $roleIds[$role->value] ?? null;

            $grantedByRole[$role->value] = $roleId === null
                ? []
                : DB::table('role_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('role_has_permissions.role_id', $roleId)
                    ->pluck('permissions.name')
                    ->all();
        }

        return view('admin.role-feature-map', [
            'roles' => $roles,
            'permissionCatalog' => PermissionCatalog::all(),
            'categories' => PermissionCatalog::categories(),
            'grantedByRole' => $grantedByRole,
            'actorRoles' => $this->actor->roles(),
            'actorIsSystemAdmin' => $this->actor->hasRole(Role::SystemAdmin->value),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string'],
        ]);

        $payload = $validated['permissions'] ?? [];

        $knownRoleNames = array_map(static fn (Role $r): string => $r->value, Role::cases());
        $knownPermissionNames = array_keys(PermissionCatalog::all());
        $actorRoleNames = $this->actor->roles();

        // Pagar anti-eskalasi-diri: kalau payload MENYEBUT role yang
        // sedang dipegang aktor sama sekali (baik menambah maupun
        // mengurangi izinnya), tolak SELURUH request sebelum apa pun
        // ditulis — pre-flight atas seluruh payload, bukan per-baris,
        // supaya tidak ada penulisan sebagian yang lolos.
        //
        // system_admin DIKECUALIKAN dari pagar ini (keputusan eksplisit
        // pengguna) — dianggap peran paling dipercaya untuk mengelola
        // akses itu sendiri, termasuk perannya sendiri. Peran lain
        // (hr_approver dkk.) TETAP terkunci seperti sebelumnya.
        if (! in_array(Role::SystemAdmin->value, $actorRoleNames, true)) {
            foreach (array_keys($payload) as $roleName) {
                abort_if(
                    in_array($roleName, $actorRoleNames, true),
                    403,
                    'Tidak dapat mengubah izin peran yang sedang Anda pegang sendiri — minta admin lain melakukannya.',
                );
            }
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        $changes = [];

        foreach ($payload as $roleName => $permissionNames) {
            if (! in_array($roleName, $knownRoleNames, true)) {
                continue;
            }

            $roleId = $roleIds[$roleName] ?? null;

            if ($roleId === null) {
                continue;
            }

            $currentPermissionNames = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $roleId)
                ->pluck('permissions.name')
                ->all();

            $desiredPermissionNames = array_values(array_intersect(
                array_unique($permissionNames),
                $knownPermissionNames,
            ));

            $toGrant = array_diff($desiredPermissionNames, $currentPermissionNames);
            $toRevoke = array_diff($currentPermissionNames, $desiredPermissionNames);

            // Independensi Auditor (§6.3, lihat Role::isReadOnly()) ditegakkan
            // di sini juga — SegregationOfDutyPolicy hanya menjaga peran×peran
            // (satu orang tidak boleh MEMEGANG Auditor + peran operasional
            // sekaligus), tapi halaman ini menulis peran×IZIN langsung, jalur
            // terpisah yang tidak pernah melewati policy itu. Tanpa pagar ini,
            // siapa pun yang bisa membuka halaman ini (hr_approver/system_admin)
            // bisa diam-diam menjadikan Auditor pemutus/pencair (mis. memberi
            // *.decide atau *.manage), meruntuhkan independensinya lewat jalur
            // yang berbeda dari yang sudah dijaga assignRole().
            if ($roleName === Role::Auditor->value) {
                foreach ($toGrant as $permissionName) {
                    abort_if(
                        ! str_ends_with($permissionName, '.view'),
                        422,
                        "Auditor harus tetap hanya-baca (independensi §6.3) — \"{$permissionName}\" bukan izin lihat-saja, tidak dapat diberikan ke peran ini.",
                    );
                }
            }

            foreach ($toGrant as $permissionName) {
                $changes[] = ['role' => $roleName, 'roleId' => $roleId, 'permission' => $permissionName, 'permissionId' => $permissionIds[$permissionName], 'grant' => true];
            }

            foreach ($toRevoke as $permissionName) {
                $changes[] = ['role' => $roleName, 'roleId' => $roleId, 'permission' => $permissionName, 'permissionId' => $permissionIds[$permissionName], 'grant' => false];
            }
        }

        if ($changes === []) {
            return redirect()->route('sysadmin.role-map.index')->with('sukses', 'Tidak ada perubahan.');
        }

        DB::transaction(function () use ($changes, $request): void {
            foreach ($changes as $change) {
                if ($change['grant']) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $change['permissionId'],
                        'role_id' => $change['roleId'],
                    ]);
                } else {
                    DB::table('role_has_permissions')
                        ->where('permission_id', $change['permissionId'])
                        ->where('role_id', $change['roleId'])
                        ->delete();
                }

                $this->auditRepository->append(new AuditEntry(
                    occurredAt: new DateTimeImmutable,
                    actor: new AuditActor(
                        actorId: $this->actor->employeeId(),
                        actorRole: implode(',', $this->actor->roles()),
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent(),
                    ),
                    auditableType: 'role_permission',
                    // role_id/permission_id (bigint bawaan spatie/laravel-permission)
                    // bukan uuid seperti kolom auditable_id — tidak ada
                    // identitas domain berupa uuid untuk pasangan
                    // peran×izin, jadi id sintetis per entri di sini,
                    // rincian sungguhan (nama peran+izin) ada di
                    // old/new_values.
                    auditableId: (string) Uuid7::generate(),
                    action: $change['grant'] ? AuditAction::PermissionGranted : AuditAction::PermissionRevoked,
                    oldValues: $change['grant'] ? null : ['role' => $change['role'], 'permission' => $change['permission']],
                    newValues: $change['grant'] ? ['role' => $change['role'], 'permission' => $change['permission']] : null,
                ));
            }
        });

        // Menulis role_has_permissions lewat DB::table() langsung (bukan
        // $role->givePermissionTo()) TIDAK memicu pembersihan cache
        // bawaan spatie/laravel-permission (default 24 jam) — tanpa
        // baris ini, izin yang baru diubah baru berlaku setelah cache
        // itu kedaluwarsa, membatalkan janji "tanpa deploy" fitur ini.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('sysadmin.role-map.index')->with('sukses', 'Perubahan izin peran disimpan.');
    }
}
