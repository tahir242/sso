<?php

namespace SsoClient;

/**
 * The signed-in user, as described by the SSO's ID token.
 */
final class Identity
{
    /**
     * @param  array<int, string>  $roles  This application's roles only
     * @param  array<int, string>  $amr  How the user authenticated, e.g. ['pwd', 'otp']
     * @param  array<string, mixed>  $claims  All ID token claims
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
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $string = fn (string $key): ?string => isset($claims[$key]) && $claims[$key] !== '' ? (string) $claims[$key] : null;

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
}
