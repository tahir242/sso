<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\RoleResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Roles', weight: 2)]
class RolesController extends ApiController
{
    /**
     * List roles
     *
     * Scope: `roles.read`. The application's roles, as sent in the `roles` claim.
     * Roles are managed by application owners in the SSO admin console.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = $this->application($request)->roles()
            ->withCount(['assignments as users_count' => fn ($q) => $q->current()])
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }
}
