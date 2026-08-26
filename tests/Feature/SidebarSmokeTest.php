<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class SidebarSmokeTest extends TestCase
{
    public function test_sidebar_renders_for_every_seeded_role(): void
    {
        $roles = ['hr_admin', 'hr_approver', 'system_admin', 'atasan_langsung', 'pimpinan_kantor', 'auditor'];

        foreach ($roles as $role) {
            $user = User::whereHas('roles', fn ($q) => $q->where('name', $role))->first();

            if ($user === null) {
                continue;
            }

            $landing = $user->hasRole('system_admin') ? 'sysadmin.users.index' : 'ess.dashboard';

            $response = $this->actingAs($user)->get(route($landing));
            $response->assertStatus(200);
            $response->assertDontSee('ErrorException', false);
            $response->assertDontSee('Undefined variable', false);
            $response->assertDontSee('Route [', false);
        }
    }
}
