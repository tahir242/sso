<?php

use App\Domain\Federation\Actions\RegisterApplication;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Directory\ActiveDirectory;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\LdapSettings;
use App\Domain\Settings\MfaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Testing\EmulatedConnectionFake;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Media files must never land in the real storage folder: the in-memory
    // test database restarts ids at 1, which would overwrite real files.
    ->beforeEach(fn () => Storage::fake('media'))
    ->in('Feature');

/**
 * Turns AD sign-in on and swaps the real LDAP connection for LdapRecord's
 * in-memory directory emulator.
 */
function fakeActiveDirectory(array $overrides = []): EmulatedConnectionFake
{
    $settings = app(LdapSettings::class);
    $settings->fill([
        'enabled' => true,
        'hosts' => ['dc01.example.test'],
        'base_dn' => 'DC=example,DC=test',
        'bind_username' => 'svc-sso@example.test',
        ...$overrides,
    ])->save();

    app(ActiveDirectory::class)->reset();
    app(ActiveDirectory::class)->connection();

    return DirectoryEmulator::setup('default');
}

/**
 * A user with two-step verification already enabled; returns [user, secret].
 *
 * @return array{0: User, 1: string}
 */
function userWithMfa(array $attributes = []): array
{
    $secret = app(Google2FA::class)->generateSecretKey(32);

    $user = User::factory()->create(['password' => 'correct-horse-battery', ...$attributes]);
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

    return [$user, $secret];
}

function currentOtp(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/**
 * Makes every IP (including the test client's 127.0.0.1) count as off-network.
 */
function treatEveryoneAsOffNetwork(): void
{
    $settings = app(MfaSettings::class);
    $settings->trusted_networks = [];
    $settings->save();
}

function superAdmin(array $attributes = []): User
{
    return tap(User::factory()->create($attributes), fn (User $u) => $u->assignRole(Role::findOrCreate('super-admin', 'web')));
}

function makeApp(string $name, ?User $owner = null): Application
{
    return app(RegisterApplication::class)->handle([
        'slug' => str($name)->slug()->toString(),
        'name' => $name,
        'base_url' => 'https://'.str($name)->slug().'.example.test',
        'owner_user_id' => $owner?->id,
        'active' => true,
    ], ['https://'.str($name)->slug().'.example.test/callback'])[0];
}

/**
 * @return array{0: Application, 1: string}
 */
function apiApp(string $slug): array
{
    return app(RegisterApplication::class)->handle([
        'slug' => $slug, 'name' => strtoupper($slug), 'base_url' => "https://{$slug}.example.test", 'active' => true,
    ], ["https://{$slug}.example.test/callback"]);
}

function clientToken(Application $app, string $secret, string $scope = 'users.read'): string
{
    return test()->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $app->oauth_client_id,
        'client_secret' => $secret,
        'scope' => $scope,
    ])->assertOk()->json('access_token');
}

/**
 * True on SQL Server older than 2017, which Laravel 13 does not support
 * (e.g. its schema inspection uses STRING_AGG). The dev instance is 2014.
 */
function onUnsupportedSqlServer(): bool
{
    if (DB::getDriverName() !== 'sqlsrv') {
        return false;
    }

    return (int) DB::selectOne("SELECT CAST(SERVERPROPERTY('ProductVersion') AS varchar(50)) AS v")->v < 14;
}
