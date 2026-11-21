<?php

namespace App\Domain\Requests\Actions;

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\NewStaffStatus;
use App\Domain\Requests\Models\NewStaffRequest;
use App\Domain\Requests\Notifications\RequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Stores a new-staff account request (no sign-in needed) and asks the
 * department's head to confirm it. Without a HOD on record, super-admins
 * confirm instead.
 */
final class SubmitNewStaffRequest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?string $ip): NewStaffRequest
    {
        $department = Department::findOrFail($data['department_id']);

        $request = NewStaffRequest::create([
            'full_name' => trim($data['full_name']),
            'emp_id' => $data['emp_id'] ?: null,
            'email' => $data['email'] ?: null,
            'mobile' => $data['mobile'] ?: null,
            'department_id' => $department->getKey(),
            'designation' => $data['designation'] ?: null,
            'joining_date' => $data['joining_date'] ?: null,
            'application_ids' => array_values(array_map('intval', $data['application_ids'] ?? [])),
            'justification' => $data['justification'] ?: null,
            'requester_ip' => $ip,
            'status' => NewStaffStatus::Pending,
        ]);

        Notification::send(self::approvers($department), new RequestNotification(
            __('New staff account to confirm: :name', ['name' => $request->full_name]),
            __(':name (:designation) says they are joining :department. Please confirm.', [
                'name' => $request->full_name,
                'designation' => $request->designation ?: __('no designation given'),
                'department' => $department->name,
            ]),
            route('approvals.new-staff'),
        ));

        return $request;
    }

    /**
     * @return Collection<int, User>
     */
    public static function approvers(Department $department): Collection
    {
        if ($department->hod) {
            return collect([$department->hod]);
        }

        return Role::where('name', 'super-admin')->exists() ? User::role('super-admin')->get() : collect();
    }
}
