<?php

namespace App\Domain\Federation\Models;

use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ChangeRequest;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An application that signs users in through the SSO (OIDC client).
 */
#[Fillable([
    'slug', 'name', 'description', 'base_url', 'oauth_client_id', 'post_logout_redirect_uris',
    'backchannel_logout_uri', 'owner_user_id', 'active', 'approval_policy', 'api_scopes',
    'access_mode', 'require_pkce',
])]
class Application extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'post_logout_redirect_uris' => 'array',
            'approval_policy' => 'array',
            'api_scopes' => 'array',
            'active' => 'boolean',
            'access_mode' => AccessMode::class,
            'require_pkce' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A disabled application cannot sign anyone in or refresh tokens.
        static::saved(function (self $application): void {
            if ($application->wasChanged('active') || $application->wasRecentlyCreated) {
                $application->client?->forceFill(['revoked' => ! $application->active])->save();
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ApplicationRole::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ApplicationAssignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ApplicationAssignment::class)
            ->withPivot(['id', 'active', 'expires_at', 'granted_by'])
            ->withTimestamps();
    }

    /**
     * Whether an administrator enabled these API scopes for this application.
     *
     * @param  array<int, string>  $scopes
     */
    public function allowsApiScopes(array $scopes): bool
    {
        return array_diff($scopes, $this->api_scopes ?? []) === [];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * The user's live assignment (active and not expired), if any.
     */
    public function assignmentFor(User $user): ?ApplicationAssignment
    {
        return $this->assignments()->current()->where('user_id', $user->getKey())->first();
    }

    public function isOpenToEveryone(): bool
    {
        return $this->access_mode === AccessMode::Everyone;
    }

    /**
     * Whether the user may sign in to this application: it is open to
     * everyone, or the user has a live assignment.
     */
    public function canBeOpenedBy(?User $user): bool
    {
        if (! $user || ! $this->active || ! $user->status->canSignIn()) {
            return false;
        }

        return $this->isOpenToEveryone() || $this->assignmentFor($user) !== null;
    }

    /**
     * The user's application role keys: from their assignment, or the
     * default roles when the application is open to everyone.
     *
     * @return array<int, string>
     */
    public function roleKeysFor(User $user): array
    {
        $assignment = $this->assignmentFor($user);

        if ($assignment) {
            return $assignment->roleKeys();
        }

        return $this->isOpenToEveryone()
            ? $this->roles()->where('is_default', true)->orderBy('key')->pluck('key')->all()
            : [];
    }

    /**
     * Active applications the user can open.
     */
    public function scopeOpenableBy(Builder $query, User $user): void
    {
        $query->active()->where(fn (Builder $q) => $q
            ->where('access_mode', AccessMode::Everyone)
            ->orWhereHas('assignments', fn (Builder $a) => $a->current()->where('user_id', $user->getKey())));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
