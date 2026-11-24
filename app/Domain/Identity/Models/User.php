<?php

namespace App\Domain\Identity\Models;

use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Identity\Actions\SignOutEverywhere;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Support\EmployeeInitials;
use App\Domain\Identity\Support\ProfilePhoto;
use App\Domain\Notifications\Channel;
use App\Domain\Notifications\GreenApi;
use App\Domain\Notifications\NotificationCategory;
use App\Domain\Settings\NotificationSettings;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'username', 'emp_id', 'emp_initial', 'name', 'given_name', 'middle_name', 'family_name', 'nickname',
    'email', 'mobile', 'gender', 'dob', 'designation',
    'street_address', 'locality', 'region', 'postal_code', 'country', 'picture',
    'department_id', 'location_id', 'auth_source', 'ldap_guid', 'password', 'legacy_md5', 'status', 'password_changed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasAvatar, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasRoles, HasUlids, LogsActivity, Notifiable;

    /** Roles that may enter the /admin console. */
    public const array ADMIN_ROLES = ['super-admin', 'app-owner', 'helpdesk', 'auditor'];

    /** Roles that approve access requests in the portal (no console access). */
    public const array APPROVER_ROLES = ['it-security'];

    /**
     * Integer primary key stays internal; the ULID is the public, immutable
     * identifier (used as the OIDC `sub` claim and in URLs).
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'password' => 'hashed',
            'legacy_md5' => 'boolean',
            'auth_source' => AuthSource::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Channels this user can receive on right now (has an email, a mobile
     * number, a browser subscription; the channel is configured).
     *
     * @return array<int, Channel>
     */
    public function availableChannels(): array
    {
        $settings = app(NotificationSettings::class);

        return array_values(array_filter(Channel::cases(), fn (Channel $channel) => match ($channel) {
            Channel::Mail => (bool) $this->email,
            Channel::WhatsApp => $settings->whatsappReady() && app(GreenApi::class)->chatId($this->mobile) !== null,
            Channel::WebPush => $settings->webpushReady() && $this->pushSubscriptions()->exists(),
        }));
    }

    /**
     * The channels to use for a category: the user's choice (or the
     * category default), limited to what is available.
     *
     * @return array<int, Channel>
     */
    public function notificationChannels(NotificationCategory $category): array
    {
        $chosen = array_key_exists($category->value, $this->notification_preferences ?? [])
            ? array_filter(array_map(Channel::tryFrom(...), (array) $this->notification_preferences[$category->value]))
            : $category->defaultChannels();

        $available = $this->availableChannels();
        $channels = array_values(array_filter($chosen, fn (Channel $c) => in_array($c, $available, true)));

        // Own-account security alerts always reach at least one outside channel.
        if ($channels === [] && $category->isMandatory() && $available !== []) {
            $channels = [$available[0]];
        }

        return $channels;
    }

    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->mobile;
    }

    /** Gender choices (OIDC `gender` claim: "female" and "male" are the defined values). */
    public const array GENDERS = ['female' => 'Female', 'male' => 'Male', 'other' => 'Other'];

    /**
     * Full name from its parts: "Jane" + "Ann" + "Doe" -> "Jane Ann Doe".
     */
    public static function composeName(?string $given, ?string $middle, ?string $family): string
    {
        return trim((string) preg_replace('/\s+/', ' ', implode(' ', [(string) $given, (string) $middle, (string) $family])));
    }

    /**
     * The stored gender as an OIDC value ("M"/"F" from the old SSO become male/female).
     */
    public function oidcGender(): ?string
    {
        $value = mb_strtolower(trim((string) $this->gender));

        return match (true) {
            $value === '' => null,
            in_array($value, ['m', 'male'], true) => 'male',
            in_array($value, ['f', 'female'], true) => 'female',
            default => $value,
        };
    }

    protected static function booted(): void
    {
        // The full name and its parts stay in step: a missing full name is built
        // from the parts; when only a full name is given (e.g. a new-staff
        // request) the parts start from it and can be corrected on the user form.
        static::saving(function (self $user): void {
            if (blank($user->name)) {
                $user->name = self::composeName($user->given_name, $user->middle_name, $user->family_name);
            } elseif (blank($user->given_name) && blank($user->family_name)) {
                $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
                $user->given_name = mb_substr($parts[0] ?? '', 0, 100) ?: null;
                $user->family_name = mb_substr($parts[1] ?? '', 0, 100) ?: null;
            }
        });

        // Every new account gets unique employee initials unless they were given.
        static::creating(function (self $user): void {
            if (blank($user->emp_initial) && filled($user->name)) {
                $user->emp_initial = EmployeeInitials::generate((string) $user->name);
            }
        });

        // A replaced or removed profile photo is deleted from disk.
        static::updated(function (self $user): void {
            if ($user->wasChanged('picture')) {
                ProfilePhoto::delete($user->getOriginal('picture'));
            }
        });

        static::deleted(fn (self $user) => ProfilePhoto::delete($user->picture));

        // Disabling or locking an account ends all of its sign-ins at once.
        static::updated(function (self $user): void {
            if ($user->wasChanged('status') && ! $user->status->canSignIn()) {
                app(SignOutEverywhere::class)->handle($user);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ApplicationAssignment::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isAdministrator(): bool
    {
        return $this->hasAnyRole(self::ADMIN_ROLES);
    }

    /** Profile photo in the admin console; without one Filament shows initials. */
    public function getFilamentAvatarUrl(): ?string
    {
        return ProfilePhoto::url($this);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status->canSignIn() && $this->isAdministrator();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['username', 'emp_id', 'name', 'given_name', 'family_name', 'email', 'department_id', 'location_id', 'auth_source', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
