<?php

namespace App\Http\Controllers\Api;

use App\Domain\Federation\Models\ApplicationAssignment;
use App\Http\Resources\Api\ApplicationUserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Users who have (or had) access to the calling application. An application
 * can never see users outside its own assignments.
 */
#[Group('Users', weight: 1)]
class ApplicationUsersController extends ApiController
{
    /**
     * List users
     *
     * Scope: `users.read`. Sorted by user ID.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            /** Matches name, username or employee ID. */
            'search' => ['nullable', 'string', 'max:100'],
            /** true = only current access; false = only expired or removed access. */
            'active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            /** @default 25 */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;

        $page = $this->assignments($request)
            ->when($search, fn (Builder $query) => $query->whereHas('user', fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('emp_id', 'like', "%{$search}%")))
            ->when(isset($validated['active']), fn (Builder $query) => $validated['active']
                ? $query->current()
                : $query->whereNot(fn (Builder $q) => $q->current()))
            ->orderBy('user_id')
            ->paginate($validated['per_page'] ?? 25);

        return ApplicationUserResource::collection($page->getCollection())
            ->additional(['meta' => $this->meta($page)]);
    }

    /**
     * Get a user
     *
     * Scope: `users.read`. 404 if the user never had access to this application.
     */
    public function show(Request $request, int $userId): ApplicationUserResource
    {
        return new ApplicationUserResource($this->assignments($request)->where('user_id', $userId)->firstOrFail());
    }

    private function assignments(Request $request): Builder
    {
        return ApplicationAssignment::query()
            ->where('application_id', $this->application($request)->getKey())
            ->with(['user.department', 'roles']);
    }
}
