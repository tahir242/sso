<?php

namespace App\Domain\Federation\Actions;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;

/**
 * Gives (or updates) a user's access to an application with the given roles.
 * With no roles chosen, the application's default roles are used.
 */
final class GrantApplicationAccess
{
    /**
     * @param  array<int, int>  $roleIds
     */
    public function handle(Application $application, User $user, array $roleIds = [], ?CarbonInterface $expiresAt = null, ?User $grantedBy = null): ApplicationAssignment
    {
        $assignment = ApplicationAssignment::updateOrCreate(
            ['application_id' => $application->getKey(), 'user_id' => $user->getKey()],
            ['active' => true, 'expires_at' => $expiresAt, 'granted_by' => $grantedBy?->getKey()],
        );

        $roleIds = $roleIds !== []
            ? $application->roles()->whereKey($roleIds)->pluck('id')->all()
            : $application->roles()->where('is_default', true)->pluck('id')->all();

        $assignment->roles()->sync($roleIds);

        return $assignment;
    }
}
