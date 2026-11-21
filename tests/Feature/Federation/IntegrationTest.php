<?php

use App\Domain\Federation\Integration\PhpKit;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Applications\Pages\ConnectApplication;
use App\Livewire\Docs\IntegrationGuide;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole(Role::findOrCreate('app-owner', 'web'));
    $this->lab = makeApp('Lab Reports', $this->owner);
});

it('shows every guide with the chosen application’s values filled in', function () {
    $this->actingAs($this->owner);

    foreach (array_keys(IntegrationGuide::PAGES) as $page) {
        $html = $this->get(route('docs.integration', ['page' => $page, 'app' => 'lab-reports']))->assertOk()->getContent();

        expect($html)->not->toContain('{{')->not->toContain('YOUR-CLIENT-ID');
    }

    $this->get(route('docs.integration', ['page' => 'python', 'app' => 'lab-reports']))
        ->assertSee($this->lab->oauth_client_id)
        ->assertSee('https://lab-reports.example.test/auth/callback')
        ->assertSee('href="api?app=lab-reports"', escape: false);
});

it('never fills in another owner’s application', function () {
    $other = makeApp('Pharmacy');
    $this->actingAs($this->owner);

    $this->get(route('docs.integration', ['page' => 'php', 'app' => 'pharmacy']))
        ->assertOk()
        ->assertSee('YOUR-CLIENT-ID')
        ->assertDontSee($other->oauth_client_id);
});

it('keeps the guides to application owners and administrators', function () {
    $this->get(route('docs.integration'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('docs.integration'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('docs.integration', ['page' => 'nope']))->assertNotFound();
});

it('builds a PHP kit that works without Composer', function () {
    $zipPath = app(PhpKit::class)->build($this->lab);
    $dir = sys_get_temp_dir().'/sso-kit-'.bin2hex(random_bytes(4));

    $zip = new ZipArchive;
    $zip->open($zipPath);
    $zip->extractTo($dir);
    $zip->close();
    unlink($zipPath);

    $config = require $dir.'/sso.php';
    expect($config)
        ->client_id->toBe($this->lab->oauth_client_id)
        ->redirect_uri->toBe('https://lab-reports.example.test/index.php?sso=callback')
        ->client_secret->toBe('PASTE-THE-CLIENT-SECRET-HERE')
        ->and(file_get_contents($dir.'/SSO-README.txt'))->toContain('Gate::protect');

    // Load the kit in a fresh PHP process, as an application would.
    $script = $dir.'/check.php';
    file_put_contents($script, '<?php require __DIR__."/sso-client/autoload.php"; echo class_exists(\SsoClient\Gate::class) && class_exists(\Firebase\JWT\JWT::class) ? "ok" : "missing";');
    expect(shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script)))->toBe('ok');

    File::deleteDirectory($dir);
});

it('lets the owner download the kit and an admin register the gate URLs', function () {
    $this->actingAs($this->owner);
    Livewire::test(ConnectApplication::class, ['record' => $this->lab->getRouteKey()])
        ->assertSee($this->lab->oauth_client_id)
        ->assertActionHidden('useGate')
        ->callAction('kit')
        ->assertFileDownloaded('lab-reports-sso-kit.zip');

    $this->actingAs(superAdmin());
    Livewire::test(ConnectApplication::class, ['record' => $this->lab->getRouteKey()])
        ->callAction('useGate');

    $lab = $this->lab->fresh();
    expect($lab->client->redirect_uris)->toBe(['https://lab-reports.example.test/callback', 'https://lab-reports.example.test/index.php?sso=callback'])
        ->and($lab->backchannel_logout_uri)->toBe('https://lab-reports.example.test/index.php?sso=backchannel-logout');
});

it('advertises the client credentials grant in discovery', function () {
    $this->getJson('/.well-known/openid-configuration')->assertJsonPath('grant_types_supported.2', 'client_credentials');
});
