<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\SecuritySettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('sso:create-admin')]
#[Description('Create a local super-admin account (first-time setup / break-glass)')]
class CreateAdmin extends Command
{
    public function handle(SecuritySettings $security): int
    {
        $username = text('Username', required: true, validate: fn (string $value) => User::where('username', $value)->exists() ? 'That username is already taken.' : null);
        $name = text('Full name', required: true);
        $email = text('Email', required: true, validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.');
        $secret = password('Password', required: true, validate: fn (string $value) => mb_strlen($value) < $security->password_min_length
            ? "Use at least {$security->password_min_length} characters."
            : null);

        $user = User::create([
            'username' => $username,
            'name' => $name,
            'email' => $email ?: null,
            'auth_source' => AuthSource::Local,
            'status' => UserStatus::Active,
            'password' => $secret,
        ]);
        $user->forceFill(['password_changed_at' => now()])->save();

        $user->assignRole(Role::findOrCreate('super-admin', 'web'));

        $this->components->info("Super-admin [{$username}] created. Enrol MFA at first sign-in.");

        return self::SUCCESS;
    }
}
