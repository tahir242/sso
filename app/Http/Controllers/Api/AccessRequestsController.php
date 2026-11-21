<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access\Actions\SubmitAccessRequest;
use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Identity\Models\User;
use App\Http\Resources\Api\AccessRequestResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Access requests for the calling application. Requests made through the
 * API go through exactly the same approvals as requests made in the portal
 * (head of department, application owner, optional IT security). The API
 * can never grant access directly.
 */
#[Group('Access requests', weight: 3)]
class AccessRequestsController extends ApiController
{
    /**
     * List access requests
     *
     * Scope: `access-requests.read`. Newest first.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(AccessRequestStatus::class)],
            /** Only requests for this SSO user ID. */
            'user_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            /** @default 25 */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->requests($request)
            ->when($validated['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($validated['user_id'] ?? null, fn (Builder $q, int $userId) => $q->where('user_id', $userId))
            ->latest()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return AccessRequestResource::collection($page->getCollection())
            ->additional(['meta' => $this->meta($page)]);
    }

    /**
     * Get an access request
     *
     * Scope: `access-requests.read`. Poll this to follow the approvals.
     */
    public function show(Request $request, string $id): AccessRequestResource
    {
        return new AccessRequestResource($this->requests($request)->where('ulid', $id)->firstOrFail());
    }

    /**
     * Request access for a user
     *
     * Scope: `access-requests.write`. Starts the normal approval chain; approvers are notified
     * and the user gets access only once every step approves. Identify the user by exactly one of
     * `user_id`, `username` or `emp_id`.
     *
     * Returns 422 if the user is unknown, already has access, or already has a pending request.
     */
    public function store(Request $request, SubmitAccessRequest $submit)
    {
        $application = $this->application($request);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'required_without_all:username,emp_id', 'prohibits:username,emp_id'],
            'username' => ['nullable', 'string', 'max:100', 'prohibits:user_id,emp_id'],
            'emp_id' => ['nullable', 'string', 'max:30', 'prohibits:user_id,username'],
            /** Role keys in this application (see `GET /roles`). Empty = the default role. */
            'roles' => ['nullable', 'array', 'max:20'],
            'roles.*' => ['string', Rule::in($application->roles()->pluck('key')->all())],
            /** Why the user needs access; shown to the approvers. */
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
            /** Last day of access (YYYY-MM-DD). Omit for permanent access. */
            'access_until' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            /** Username of the person asking on the user's behalf (an existing SSO user). Defaults to the user. */
            'requested_by' => ['nullable', 'string', 'max:100'],
        ]);

        $subject = $this->findUser($validated);
        $requester = isset($validated['requested_by'])
            ? User::where('username', $validated['requested_by'])->first()
            : $subject;

        if (! $requester || ! $requester->status->canSignIn()) {
            throw ValidationException::withMessages(['requested_by' => __('No active SSO user with that username.')]);
        }

        $accessRequest = $submit->handle(
            $requester,
            $application,
            $subject,
            $application->roles()->whereIn('key', $validated['roles'] ?? [])->pluck('id')->all(),
            $validated['justification'],
            isset($validated['access_until']) ? Carbon::parse($validated['access_until']) : null,
        );

        activity()->performedOn($accessRequest)
            ->withProperties(['client' => $application->slug, 'requested_by' => $requester->username])
            ->log('access request submitted through the API');

        return (new AccessRequestResource($accessRequest->fresh(['user', 'requester', 'steps.approver'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findUser(array $data): User
    {
        $key = collect(['user_id', 'username', 'emp_id'])->first(fn (string $k) => filled($data[$k] ?? null));
        $user = User::where($key === 'user_id' ? 'id' : $key, $data[$key])->first();

        if (! $user || ! $user->status->canSignIn()) {
            throw ValidationException::withMessages([$key => __('No active SSO user matches.')]);
        }

        return $user;
    }

    private function requests(Request $request): Builder
    {
        return AccessRequest::query()
            ->where('application_id', $this->application($request)->getKey())
            ->with(['user', 'requester', 'steps.approver']);
    }
}
