<?php

namespace App\Domain\Federation\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An application the user opened during one SSO session (`sid`, held in the
 * browser session `session_id`).
 */
#[Fillable(['sid', 'session_id', 'user_id', 'oauth_client_id', 'auth_time', 'amr', 'last_authorized_at', 'logged_out_at'])]
class ClientSession extends Model
{
    protected function casts(): array
    {
        return [
            'auth_time' => 'immutable_datetime',
            'amr' => 'array',
            'last_authorized_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }
}
