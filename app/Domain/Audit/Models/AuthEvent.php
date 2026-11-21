<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\SecurityAlerts;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Append-only security log (sign-ins, MFA, devices). Retention/archiving
 * is handled by the audit phase.
 */
#[Fillable(['user_id', 'event', 'identifier', 'ip_address', 'user_agent', 'meta', 'device_hash', 'created_at'])]
class AuthEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => AuthEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $event) => app(SecurityAlerts::class)->handleAuthEvent($event));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  string|null  $deviceHash  Hash of the browser's device cookie (sign-ins only)
     */
    public static function record(AuthEventType $event, ?User $user = null, ?string $identifier = null, array $meta = [], ?Request $request = null, ?string $deviceHash = null): self
    {
        $request ??= request();

        return static::create([
            'user_id' => $user?->getKey(),
            'event' => $event,
            'identifier' => $identifier !== null ? Str::limit($identifier, 97) : $user?->username,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 497),
            'meta' => $meta ?: null,
            'device_hash' => $deviceHash,
            'created_at' => now(),
        ]);
    }
}
