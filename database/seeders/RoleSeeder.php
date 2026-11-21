<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles for the SSO itself (not client-application roles): admin-console
 * roles, plus approver roles that act in the self-service portal only.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...User::ADMIN_ROLES, ...User::APPROVER_ROLES] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
