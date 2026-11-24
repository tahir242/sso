<?php

namespace App\Domain\Federation\Oidc;

use App\Domain\Federation\ApiScopes;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\ProfilePhoto;
use App\Domain\Settings\GeneralSettings;

/**
 * Which user claims each scope releases. Clients only receive what they ask
 * for, and `roles` only ever contains roles of the requesting application.
 */
final class Claims
{
    /** @var array<string, string> scope => description (also the Passport scope list) */
    public const array SCOPES = [
        'openid' => 'Sign you in',
        'profile' => 'Your name and username',
        'email' => 'Your email address',
        'phone' => 'Your phone number',
        'address' => 'Your postal address',
        'employee' => 'Your employee ID, department and designation',
        'roles' => 'Your roles in this application',
        'applications' => 'The applications you can open and your roles in them',
    ];

    /**
     * Scopes a normal client asks for. `applications` is only for clients an
     * administrator allowed to read assignments (ApiScopes::ASSIGNMENTS_READ),
     * such as a reporting application.
     */
    public const array CLIENT_SCOPES = ['openid', 'profile', 'email', 'employee', 'roles'];

    /**
     * @param  array<int, string>  $scopes
     * @return array<string, mixed>
     */
    public function for(User $user, ?Application $application, array $scopes): array
    {
        $claims = [];

        // Standard claims (OpenID Connect Core 1.0, section 5.1), each from its
        // own profile field. Empty fields are left out.
        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
            $claims['given_name'] = $user->given_name;
            $claims['middle_name'] = $user->middle_name;
            $claims['family_name'] = $user->family_name;
            $claims['nickname'] = $user->nickname;
            $claims['preferred_username'] = $user->username;
            $claims['picture'] = ProfilePhoto::url($user);
            $claims['gender'] = $user->oidcGender();
            $claims['birthdate'] = $user->dob?->format('Y-m-d');
            // Organisation-wide values: everyone works in the same time zone and language.
            $claims['zoneinfo'] = app(GeneralSettings::class)->timezone;
            $claims['locale'] = str_replace('_', '-', (string) config('app.locale'));
            $claims['updated_at'] = $user->updated_at?->getTimestamp();
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = false;
        }

        if (in_array('phone', $scopes, true) && filled($user->mobile)) {
            $claims['phone_number'] = $user->mobile;
            $claims['phone_number_verified'] = false;
        }

        if (in_array('address', $scopes, true)) {
            $claims['address'] = self::address($user);
        }

        if (in_array('employee', $scopes, true)) {
            // Numeric SSO user id. Existing applications store it in their
            // own tables (e.g. "created by"); users migrated from the old SSO
            // kept their old UserID, so those references stay valid.
            $claims['user_id'] = $user->getKey();
            $claims['emp_id'] = $user->emp_id;
            $claims['emp_initial'] = $user->emp_initial;
            $claims['department'] = $user->department?->name;
            $claims['designation'] = $user->designation;
        }

        if (in_array('roles', $scopes, true)) {
            $claims['roles'] = $application?->roleKeysFor($user) ?? [];
        }

        // Every application the user can open, with their roles there — released
        // only to applications an administrator allowed to read assignments.
        if (in_array('applications', $scopes, true) && $application?->allowsApiScopes([ApiScopes::ASSIGNMENTS_READ])) {
            $claims['applications'] = Application::query()->openableBy($user)->orderBy('slug')->get()
                ->map(fn (Application $app) => ['slug' => $app->slug, 'name' => $app->name, 'roles' => $app->roleKeysFor($user)])
                ->values()->all();
        }

        return array_filter($claims, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The OIDC `address` claim (a JSON object), or null when no part is set.
     *
     * @return array<string, string>|null
     */
    public static function address(User $user): ?array
    {
        $parts = array_filter([
            'street_address' => $user->street_address,
            'locality' => $user->locality,
            'region' => $user->region,
            'postal_code' => $user->postal_code,
            'country' => $user->country,
        ], fn ($value) => filled($value));

        if ($parts === []) {
            return null;
        }

        // "formatted": street on the first line, then "locality region postal_code", then country.
        $cityLine = trim(implode(' ', array_filter([$parts['locality'] ?? null, $parts['region'] ?? null, $parts['postal_code'] ?? null])));

        return ['formatted' => implode("\n", array_filter([$parts['street_address'] ?? null, $cityLine, $parts['country'] ?? null])), ...$parts];
    }
}
