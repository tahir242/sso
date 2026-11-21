<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'emp_id' => (string) fake()->unique()->numberBetween(10000, 99999),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'auth_source' => AuthSource::Local,
            'status' => UserStatus::Active,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Disabled]);
    }

    public function activeDirectory(): static
    {
        return $this->state(fn () => [
            'auth_source' => AuthSource::ActiveDirectory,
            'password' => null,
            'ldap_guid' => (string) Str::uuid(),
        ]);
    }
}
