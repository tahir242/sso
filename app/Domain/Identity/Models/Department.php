<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'hod_user_id', 'active'])]
class Department extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Head of department — first approver for access requests. */
    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
