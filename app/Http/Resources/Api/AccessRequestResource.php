<?php

namespace App\Http\Resources\Api;

use App\Domain\Access\Models\AccessRequest;
use App\Domain\Access\Models\ApprovalStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessRequest
 */
class AccessRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'user' => [
                'user_id' => $this->user->getKey(),
                'username' => $this->user->username,
                'name' => $this->user->name,
                'emp_id' => $this->user->emp_id,
            ],
            'requested_by' => [
                'user_id' => $this->requester->getKey(),
                'username' => $this->requester->username,
                'name' => $this->requester->name,
            ],
            'roles' => $this->requestedRoles()->pluck('key')->all(),
            'justification' => $this->justification,
            'access_until' => $this->access_until?->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            /** Approval chain in order (head of department, application owner, IT security). */
            'steps' => $this->steps->sortBy('sequence')->values()->map(fn (ApprovalStep $step) => [
                'type' => $step->type->value,
                'status' => $step->status->value,
                'approver' => $step->approver?->name,
                'due_at' => $step->due_at?->toIso8601String(),
                'decided_at' => $step->decided_at?->toIso8601String(),
                'comment' => $step->comment,
            ])->all(),
        ];
    }
}
