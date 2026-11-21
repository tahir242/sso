<?php

namespace App\Domain\Federation\Actions;

use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Makes a user's direct application access match the administrator's
 * selection on the user form. New applications get their default roles;
 * removed ones are deactivated (kept for the audit trail, tokens revoked).
 * Only active, "assigned users only" applications are managed here.
 */
final readonly class SyncUserApplications
{
    public function __construct(private GrantApplicationAccess $grant) {}

    /**
     * @param  array<int, int|string>  $applicationIds
     * @return array{added: list<string>, removed: list<string>}
     */
    public function handle(User $user, array $applicationIds, ?User $by = null): array
    {
        $managed = Application::query()->active()->where('access_mode', AccessMode::Assigned)->get()->keyBy('id');
        $wanted = collect($applicationIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $managed->has($id))->unique();

        $current = ApplicationAssignment::query()->current()->where('user_id', $user->getKey())
            ->whereIn('application_id', $managed->keys())->get()->keyBy('application_id');

        $added = [];
        $removed = [];

        DB::transaction(function () use ($user, $by, $managed, $wanted, $current, &$added, &$removed): void {
            foreach ($wanted->diff($current->keys()) as $id) {
                $this->grant->handle($managed[$id], $user, grantedBy: $by);
                $added[] = $managed[$id]->name;
            }

            foreach ($current->keys()->diff($wanted) as $id) {
                $current[$id]->update(['active' => false]);
                $removed[] = $managed[$id]->name;
            }
        });

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * Ids of the managed applications the user currently has.
     *
     * @return list<int>
     */
    public static function currentIds(User $user): array
    {
        return ApplicationAssignment::query()->current()->where('user_id', $user->getKey())
            ->whereHas('application', fn ($q) => $q->active()->where('access_mode', AccessMode::Assigned))
            ->pluck('application_id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
