<?php

namespace App\Http\Resources\Api;

use App\Domain\Federation\Models\ApplicationAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as seen by one application: their profile plus their access to it.
 *
 * @mixin ApplicationAssignment
 */
class ApplicationUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            /** SSO user ID; the same number as the old SSO UserID for users migrated from it. */
            'user_id' => $user->getKey(),
            /** Stable OIDC subject (`sub` claim). */
            'sub' => (string) $user->ulid,
            'username' => $user->username,
            'name' => $user->name,
            'emp_id' => $user->emp_id,
            'emp_initial' => $user->emp_initial,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'department' => $user->department?->name,
            'designation' => $user->designation,
            'account_status' => $user->status->value,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'access' => [
                /** True while the assignment is active and not expired. */
                'active' => $this->isCurrent(),
                'expires_at' => $this->expires_at?->toIso8601String(),
                /** Role keys in this application. */
                'roles' => $this->roleKeys(),
                'since' => $this->created_at?->toIso8601String(),
            ],
        ];
    }
}
