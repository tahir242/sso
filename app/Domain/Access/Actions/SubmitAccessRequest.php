<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\ApprovalRouter;
use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\AccessSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an access request and starts its approval chain.
 */
final readonly class SubmitAccessRequest
{
    public function __construct(
        private ApprovalRouter $router,
        private AdvanceAccessRequest $advance,
        private AccessSettings $settings,
    ) {}

    /**
     * @param  array<int, int>  $roleIds
     *
     * @throws ValidationException
     */
    public function handle(User $requester, Application $application, User $subject, array $roleIds, string $justification, ?CarbonInterface $accessUntil = null): AccessRequest
    {
        if (! $application->active) {
            throw ValidationException::withMessages(['application' => __('This application is not available.')]);
        }

        if ($application->assignmentFor($subject)) {
            throw ValidationException::withMessages(['application' => __(':name already has access to :app.', ['name' => $subject->name, 'app' => $application->name])]);
        }

        if (AccessRequest::pending()->where('user_id', $subject->getKey())->where('application_id', $application->getKey())->exists()) {
            throw ValidationException::withMessages(['application' => __('A request for :app is already waiting for approval.', ['app' => $application->name])]);
        }

        if ($accessUntil && $this->settings->max_request_days > 0 && $accessUntil->gt(now()->addDays($this->settings->max_request_days))) {
            throw ValidationException::withMessages(['access_until' => __('Access can be requested for at most :days days.', ['days' => $this->settings->max_request_days])]);
        }

        return DB::transaction(function () use ($requester, $application, $subject, $roleIds, $justification, $accessUntil) {
            $request = AccessRequest::create([
                'application_id' => $application->getKey(),
                'user_id' => $subject->getKey(),
                'requested_by' => $requester->getKey(),
                'role_ids' => $application->roles()->whereKey($roleIds)->pluck('id')->all() ?: null,
                'justification' => trim($justification),
                'access_until' => $accessUntil?->toDateString(),
                'status' => AccessRequestStatus::Pending,
            ]);

            $this->router->createSteps($request);
            $this->advance->handle($request->fresh(['steps', 'application', 'user']));

            return $request;
        });
    }
}
