<?php

namespace App\Domain\Federation\Models;

use App\Domain\Federation\TokenRevoker;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * "User X may use application Y, with these roles" (application_user).
 */
class ApplicationAssignment extends Pivot
{
    use LogsActivity;

    protected $table = 'application_user';

    public $incrementing = true;

    protected $fillable = ['application_id', 'user_id', 'granted_by', 'expires_at', 'active'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Losing access ends the application's tokens for that user.
        static::saved(function (self $assignment): void {
            if (! $assignment->isCurrent()) {
                $assignment->revokeTokens();
            }
        });
        static::deleted(fn (self $assignment) => $assignment->revokeTokens());
    }

    private function revokeTokens(): void
    {
        $clientId = Application::whereKey($this->application_id)->value('oauth_client_id');

        app(TokenRevoker::class)->forUser((int) $this->user_id, $clientId);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ApplicationRole::class, 'application_role_user', 'application_user_id', 'application_role_id');
    }

    public function scopeCurrent(Builder $query): void
    {
        $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isCurrent(): bool
    {
        return $this->active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return array<int, string>
     */
    public function roleKeys(): array
    {
        return $this->roles->pluck('key')->sort()->values()->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
