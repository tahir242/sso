<?php

namespace App\Http\Resources\Api;

use App\Domain\Federation\Models\ApplicationRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationRole
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** Sent in the `roles` claim and accepted by `POST /access-requests`. */
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            /** Given to every user who gets access without asking for specific roles. */
            'is_default' => $this->is_default,
            /** Users who currently hold this role. */
            'users_count' => (int) ($this->users_count ?? 0),
        ];
    }
}
