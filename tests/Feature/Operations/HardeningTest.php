<?php

use App\Domain\Identity\Models\User;
use App\Support\Operations\Backup;
use App\Support\Operations\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

it('sends security headers and no version banner', function () {
    $response = $this->get('/login')->assertOk();

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By')
        ->assertHeaderMissing('Strict-Transport-Security');

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('leaves the API reference without a CSP so its viewer can load', function () {
    if (onUnsupportedSqlServer()) {
        $this->markTestSkipped('API docs need SQL Server 2017+ (schema inspection uses STRING_AGG).');
    }

    $this->actingAs(superAdmin())->get('/docs/api')->assertOk()->assertHeaderMissing('Content-Security-Policy');
});

it('uses a neutral session cookie name', function () {
    expect(config('session.cookie'))->toBe('sso_session');
});

it('throttles token requests from unknown clients hard and known clients gently', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->post('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => 'guess', 'client_secret' => 'x']);
    }
    $this->post('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => 'guess'])->assertTooManyRequests();

    [$app, $secret] = apiApp('lab');
    $this->post('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $app->oauth_client_id, 'client_secret' => $secret, 'scope' => 'users.read'])->assertOk();
});

it('answers junk client ids with an OAuth error, not a server error', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/oauth/authorize?client_id=not-a-uuid&response_type=code&redirect_uri=https://x.test/cb')->assertStatus(401);
    $this->get('/oauth/logout?client_id=not-a-uuid')->assertOk();
});

it('reports go-live readiness', function () {
    config(['session.driver' => 'database']);
    superAdmin();
    Cache::forever('heartbeat:scheduler', now()->getTimestamp());
    Cache::forever(QueueHeartbeat::KEY, now()->getTimestamp());

    $check = $this->artisan('sso:check')
        ->expectsOutputToContain('Scheduler ran in the last 5 minutes')
        ->expectsOutputToContain('Only 1 super-admin');

    // On SQL Server older than 2017 the check rightly fails on the version.
    onUnsupportedSqlServer()
        ? $check->expectsOutputToContain('older than 2017')->assertFailed()
        : $check->assertSuccessful();

    (new QueueHeartbeat)->handle();
    expect(Cache::get(QueueHeartbeat::KEY))->toBeGreaterThan(0);
});

it('fails readiness when the scheduler is not running or nobody can administer', function () {
    $this->artisan('sso:check')
        ->expectsOutputToContain('Scheduler not running')
        ->expectsOutputToContain('No super-admin')
        ->assertFailed();
});

it('makes an encrypted backup and passes the restore drill', function () {
    $dir = storage_path('framework/testing/backups-'.bin2hex(random_bytes(3)));
    config(['backup.path' => $dir, 'backup.password' => 'correct-horse-battery-staple', 'backup.keep' => 2]);

    foreach (['oauth-private.key', 'oauth-public.key'] as $key) {
        if (! is_file(storage_path($key))) {
            $this->artisan('passport:keys');
        }
    }

    $this->artisan('sso:backup')->expectsOutputToContain('Backup finished')->assertSuccessful();
    $archive = glob($dir.'/sso-backup-*.zip')[0];

    // Without the password nothing can be read.
    $zip = new ZipArchive;
    $zip->open($archive);
    expect($zip->getFromName('keys/oauth-private.key'))->toBeFalse();
    $zip->close();

    $this->artisan('sso:backup', ['--verify' => $archive])->expectsOutputToContain('complete and readable')->assertSuccessful();

    config(['backup.password' => 'wrong-password-wrong-password']);
    $this->artisan('sso:backup', ['--verify' => $archive])->assertFailed();

    File::deleteDirectory($dir);
});

it('refuses to back up without a strong password', function () {
    config(['backup.password' => 'short']);

    expect(fn () => app(Backup::class)->create())->toThrow(RuntimeException::class, 'SSO_BACKUP_PASSWORD');
});
