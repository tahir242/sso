<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser the user chose to "remember" after MFA. Only a SHA-256 hash of
 * the cookie token is stored.
 */
#[Fillable(['user_id', 'token_hash', 'label', 'ip_address', 'last_used_at', 'expires_at'])]
class TrustedDevice extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }
}
