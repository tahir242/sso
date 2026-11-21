<?php

namespace App\Domain\Requests\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Models\ChangeRequest;
use App\Domain\Requests\Notifications\RequestNotification;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The application owner moves a change request along and tells the requester.
 */
final class UpdateChangeRequest
{
    public function handle(ChangeRequest $request, User $owner, ChangeRequestStatus $status, ?string $comment = null): void
    {
        if (! self::canManage($request, $owner)) {
            throw new AuthorizationException(__('Only the application owner can update this request.'));
        }

        $request->update([
            'status' => $status,
            'owner_comment' => trim((string) $comment) ?: $request->owner_comment,
            'decided_by' => $owner->getKey(),
            'decided_at' => now(),
        ]);

        $request->requester?->notify(new RequestNotification(
            __(':app: your request is now “:status”', ['app' => $request->application->name, 'status' => $status->getLabel()]),
            '“'.$request->title.'”'.($request->owner_comment ? ' — '.$request->owner_comment : ''),
            route('access.requests', ['tab' => 'changes']),
        ));
    }

    public static function canManage(ChangeRequest $request, User $user): bool
    {
        return $user->hasRole('super-admin') || $request->application?->owner_user_id === $user->getKey();
    }
}
