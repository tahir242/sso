<?php

namespace App\Domain\Requests\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\NewStaffStatus;
use App\Domain\Requests\Models\NewStaffRequest;
use App\Domain\Requests\Notifications\RequestNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * The head of department confirms (or rejects) that the person is joining.
 * On approval the IT helpdesk is asked to create the account.
 */
final class DecideNewStaffRequest
{
    public function handle(NewStaffRequest $request, User $hod, bool $approve, ?string $comment = null): void
    {
        if (! self::canDecide($request, $hod)) {
            throw new AuthorizationException(__('Only the head of department can confirm this request.'));
        }

        $comment = trim((string) $comment) ?: null;

        if (! $approve && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('Please give a reason.')]);
        }

        $request->update([
            'status' => $approve ? NewStaffStatus::Approved : NewStaffStatus::Rejected,
            'hod_id' => $hod->getKey(),
            'hod_comment' => $comment,
            'hod_decided_at' => now(),
        ]);

        if ($approve) {
            $roles = Role::whereIn('name', ['helpdesk', 'super-admin'])->pluck('name')->all();
            $helpdesk = $roles ? User::role($roles)->get() : collect();

            Notification::send($helpdesk, new RequestNotification(
                __('Create account: :name', ['name' => $request->full_name]),
                __(':hod confirmed :name joining :department.', ['hod' => $hod->name, 'name' => $request->full_name, 'department' => $request->department->name]),
                url('/admin/new-staff-requests/'.$request->ulid),
            ));
        }

        CompleteNewStaffRequest::emailRequester($request);
    }

    public static function canDecide(NewStaffRequest $request, User $user): bool
    {
        if ($request->status !== NewStaffStatus::Pending) {
            return false;
        }

        $hodId = $request->department?->hod_user_id;

        return $hodId ? $hodId === $user->getKey() || $user->hasRole('super-admin') : $user->hasRole('super-admin');
    }
}
