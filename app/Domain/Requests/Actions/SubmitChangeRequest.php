<?php

namespace App\Domain\Requests\Actions;

use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Enums\ChangeRequestType;
use App\Domain\Requests\Enums\Priority;
use App\Domain\Requests\Models\ChangeRequest;
use App\Domain\Requests\Notifications\RequestNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * A user suggests a change or reports a problem; the application owner (or
 * the super-admins when there is none) is notified.
 */
final class SubmitChangeRequest
{
    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function handle(User $user, Application $application, ChangeRequestType $type, string $title, string $description, Priority $priority, array $attachments = []): ChangeRequest
    {
        $request = ChangeRequest::create([
            'application_id' => $application->getKey(),
            'requested_by' => $user->getKey(),
            'type' => $type,
            'title' => trim($title),
            'description' => trim($description),
            'priority' => $priority,
            'status' => ChangeRequestStatus::Submitted,
        ]);

        foreach ($attachments as $file) {
            $request->addMedia($file)->usingName($file->getClientOriginalName())->toMediaCollection('attachments');
        }

        $owners = $application->owner
            ? collect([$application->owner])
            : (Role::where('name', 'super-admin')->exists() ? User::role('super-admin')->get() : collect());

        Notification::send($owners->reject(fn (User $u) => $u->is($user)), new RequestNotification(
            __(':type for :app', ['type' => $type->getLabel(), 'app' => $application->name]),
            __(':name: “:title” (:priority priority)', ['name' => $user->name, 'title' => $request->title, 'priority' => mb_strtolower($priority->getLabel())]),
            url('/admin/change-requests/'.$request->ulid),
        ));

        return $request;
    }
}
