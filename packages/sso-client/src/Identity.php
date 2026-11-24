<?php

namespace SsoClient;

/**
 * The signed-in user, as described by the SSO's ID token.
 *
 * Standard OpenID Connect claims (OpenID Connect Core 1.0, section 5.1) come
 * from the `profile`, `email`, `phone` and `address` scopes; the employee
 * claims (`user_id`, `emp_id`, `emp_initial`, `department`, `designation`)
 * from the SSO's own `employee` scope. A claim the SSO did not send is null.
 */
final class Identity
{
    /**
     * @param  array<int, string>  $roles  This application's roles only
     * @param  array<int, string>  $amr  How the user authenticated, e.g. ['pwd', 'otp']
     * @param  array<string, mixed>  $claims  All ID token claims
     * @param  string|null  $givenName  First name (`given_name`)
     * @param  string|null  $middleName  Middle name (`middle_name`)
     * @param  string|null  $familyName  Last name / surname (`family_name`)
     * @param  string|null  $nickname  Name the person is known by (`nickname`)
     * @param  string|null  $picture  Absolute URL of the profile photo (`picture`)
     * @param  string|null  $gender  "female", "male" or "other" (`gender`)
     * @param  string|null  $birthdate  Date of birth as YYYY-MM-DD (`birthdate`)
     * @param  string|null  $phoneNumber  Mobile number (`phone_number`; needs the `phone` scope)
     * @param  array{formatted?: string, street_address?: string, locality?: string, region?: string, postal_code?: string, country?: string}|null  $address  Postal address (`address`; needs the `address` scope)
     * @param  string|null  $zoneinfo  Time zone, e.g. Asia/Karachi (`zoneinfo`)
     * @param  string|null  $locale  Language, e.g. en (`locale`)
     */
    public function __construct(
        public readonly string $sub,
        public readonly ?int $userId,
        public readonly ?string $username,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?string $empId,
        public readonly ?string $empInitial,
        public readonly ?string $department,
        public readonly ?string $designation,
        public readonly array $roles,
        public readonly ?string $sid,
        public readonly array $amr,
        public readonly array $claims,
        public readonly ?string $givenName = null,
        public readonly ?string $middleName = null,
        public readonly ?string $familyName = null,
        public readonly ?string $nickname = null,
        public readonly ?string $picture = null,
        public readonly ?string $gender = null,
        public readonly ?string $birthdate = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?array $address = null,
        public readonly ?string $zoneinfo = null,
        public readonly ?string $locale = null,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $string = fn (string $key): ?string => isset($claims[$key]) && is_scalar($claims[$key]) && $claims[$key] !== '' ? (string) $claims[$key] : null;

        $address = isset($claims['address']) && is_array($claims['address'])
            ? array_map('strval', array_filter($claims['address'], fn ($value) => is_scalar($value) && $value !== ''))
            : null;

        return new self(
            sub: (string) $claims['sub'],
            userId: isset($claims['user_id']) ? (int) $claims['user_id'] : null,
            username: $string('preferred_username'),
            name: $string('name'),
            email: $string('email'),
            empId: $string('emp_id'),
            empInitial: $string('emp_initial'),
            department: $string('department'),
            designation: $string('designation'),
            roles: array_values(array_map('strval', (array) ($claims['roles'] ?? []))),
            sid: $string('sid'),
            amr: array_values((array) ($claims['amr'] ?? [])),
            claims: $claims,
            givenName: $string('given_name'),
            middleName: $string('middle_name'),
            familyName: $string('family_name'),
            nickname: $string('nickname'),
            picture: $string('picture'),
            gender: $string('gender'),
            birthdate: $string('birthdate'),
            phoneNumber: $string('phone_number'),
            address: $address ?: null,
            zoneinfo: $string('zoneinfo'),
            locale: $string('locale'),
        );
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function usedTwoStepVerification(): bool
    {
        return in_array('otp', $this->amr, true);
    }

    /**
     * The name to greet the person with: nickname, else first name, else full name.
     */
    public function displayName(): ?string
    {
        return $this->nickname ?? $this->givenName ?? $this->name ?? $this->username;
    }
}
