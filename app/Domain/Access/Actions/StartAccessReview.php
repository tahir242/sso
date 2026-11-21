<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\AccessReviewCampaign;
use App\Domain\Access\Notifications\AccessReviewAssigned;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Federation\Models\ClientSession;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Snapshots everyone's current access (one application, or all) and asks each
 * application owner to confirm it. Applications without an owner go to the
 * super-admins.
 */
final class StartAccessReview
{
    public function handle(string $name, ?Application $application, CarbonInterface $dueAt, bool $autoRevoke, ?User $createdBy = null, bool $scheduled = false): AccessReviewCampaign
    {
        if ($application && AccessReviewCampaign::open()->where('application_id', $application->getKey())->exists()) {
            throw ValidationException::withMessages(['application_id' => __('A review of :app is already open.', ['app' => $application->name])]);
        }

        $campaign = DB::transaction(function () use ($name, $application, $dueAt, $autoRevoke, $createdBy, $scheduled) {
            $campaign = AccessReviewCampaign::create([
                'name' => $name,
                'application_id' => $application?->getKey(),
                'due_at' => $dueAt,
                'auto_revoke' => $autoRevoke,
                'scheduled' => $scheduled,
                'status' => AccessReviewCampaign::OPEN,
                'created_by' => $createdBy?->getKey(),
            ]);

            $assignments = ApplicationAssignment::query()
                ->current()
                ->whereHas('application', fn ($q) => $q->where('active', true))
                ->when($application, fn ($q) => $q->where('application_id', $application->getKey()))
                ->with(['application', 'roles'])
                ->get();

            $lastUsed = ClientSession::query()
                ->selectRaw('user_id, oauth_client_id, max(last_authorized_at) as last_used')
                ->groupBy('user_id', 'oauth_client_id')
                ->get()
                ->keyBy(fn ($row) => $row->user_id.'|'.$row->oauth_client_id);

            foreach ($assignments as $assignment) {
                $ownerId = $assignment->application->owner_user_id;

                $campaign->items()->create([
                    'assignment_id' => $assignment->getKey(),
                    'application_id' => $assignment->application_id,
                    'user_id' => $assignment->user_id,
                    // Owners never review their own access.
                    'reviewer_id' => $ownerId === $assignment->user_id ? null : $ownerId,
                    'roles_snapshot' => $assignment->roleKeys(),
                    'access_since' => $assignment->created_at,
                    'last_used_at' => $lastUsed[$assignment->user_id.'|'.$assignment->application->oauth_client_id]->last_used ?? null,
                ]);
            }

            return $campaign;
        });

        $this->notifyReviewers($campaign);

        return $campaign;
    }

    private function notifyReviewers(AccessReviewCampaign $campaign): void
    {
        $counts = $campaign->items()->toBase()->selectRaw('reviewer_id, count(*) as total')->groupBy('reviewer_id')->pluck('total', 'reviewer_id');

        foreach ($counts as $reviewerId => $total) {
            $reviewers = $reviewerId ? User::whereKey($reviewerId)->get() : $this->superAdmins();
            Notification::send($reviewers, new AccessReviewAssigned($campaign, (int) $total));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function superAdmins(): Collection
    {
        return Role::where('name', 'super-admin')->exists() ? User::role('super-admin')->get() : collect();
    }
}
