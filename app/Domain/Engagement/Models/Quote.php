<?php

namespace App\Domain\Engagement\Models;

use App\Domain\Engagement\Services\LoginGreeting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['text', 'author', 'active'])]
class Quote extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => LoginGreeting::forget());
        static::deleted(fn () => LoginGreeting::forget());
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
