<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Enums\ApprovalStepType;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

#[Fillable([
    'access_request_id', 'sequence', 'type', 'approver_id', 'status', 'decided_by',
    'comment', 'activated_at', 'due_at', 'escalated_at', 'decided_at',
])]
class ApprovalStep extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ApprovalStepType::class,
            'status' => ApprovalStepStatus::class,
            'activated_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class, 'access_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Pending steps the user may decide: named to them, or unnamed steps whose
     * fallback role they hold. Never their own access.
     */
    public function scopeAwaiting(Builder $query, User $user): void
    {
        $query->where('status', ApprovalStepStatus::Pending)
            ->whereHas('request', fn (Builder $q) => $q->where('user_id', '!=', $user->getKey()))
            ->where(function (Builder $q) use ($user) {
                $q->where('approver_id', $user->getKey());

                foreach (ApprovalStepType::cases() as $type) {
                    if ($user->hasAnyRole($type->fallbackRoles())) {
                        $q->orWhere(fn (Builder $q) => $q->whereNull('approver_id')->where('type', $type));
                    }
                }
            });
    }

    public function canBeDecidedBy(User $user): bool
    {
        if ($this->status !== ApprovalStepStatus::Pending || $this->request->user_id === $user->getKey()) {
            return false;
        }

        if ($this->approver_id !== null) {
            // Super-admins may step in for a named approver (e.g. on leave).
            return $this->approver_id === $user->getKey() || $user->hasRole('super-admin');
        }

        return $user->hasAnyRole($this->type->fallbackRoles());
    }

    /**
     * People to notify when this step becomes active or overdue.
     *
     * @return Collection<int, User>
     */
    public function notifiables(): Collection
    {
        if ($this->approver_id) {
            return User::whereKey($this->approver_id)->get();
        }

        // Only roles that exist (User::role() throws for unknown role names).
        $roles = Role::whereIn('name', $this->type->fallbackRoles())->pluck('name')->all();

        return $roles === []
            ? new Collection
            : User::role($roles)->where('id', '!=', $this->request->user_id)->get();
    }

    public function isOverdue(): bool
    {
        return $this->status === ApprovalStepStatus::Pending && $this->due_at?->isPast();
    }
}
