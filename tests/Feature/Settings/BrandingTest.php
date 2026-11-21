<?php

use App\Domain\Identity\Models\User;
use App\Domain\Settings\Branding;
use App\Domain\Settings\GeneralSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings\ManageGeneral;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake(Branding::DISK));

function uploadLogo(UploadedFile $file)
{
    // Like the upload panel: a new file takes the place of the current one.
    return Livewire::actingAs(superAdmin())->test(ManageGeneral::class)
        ->fillForm(['logo_path' => [$file]])
        ->call('save');
}

function svgLogo(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'.$body.'</svg>');
}

it('shows the built-in mark when no logo is uploaded', function () {
    expect(Branding::logoUrl())->toBeNull();

    $this->get(route('login'))->assertOk()
        ->assertSee('aria-label="'.config('app.name').'"', false)
        ->assertDontSee('/branding/logo/');
});

it('uploads a logo and shows it on the sign-in screen, the portal and the console header', function () {
    uploadLogo(UploadedFile::fake()->image('logo.png', 300, 120))->assertHasNoFormErrors();

    $path = app(GeneralSettings::class)->logo_path;
    expect($path)->toStartWith('branding/')->toEndWith('.png');
    Storage::disk(Branding::DISK)->assertExists($path);

    $url = Branding::logoUrl();
    auth()->logout();
    $this->get(route('login'))->assertOk()->assertSee($url, false);
    $this->actingAs(User::factory()->create())->get(route('home'))->assertSee($url, false);
    $this->actingAs(superAdmin())->get(Dashboard::getUrl())->assertOk()->assertSee($url, false);
});

it('serves the logo publicly with its real type, long caching and a sandboxing CSP', function () {
    uploadLogo(UploadedFile::fake()->image('logo.png', 200, 80));
    auth()->logout();

    $response = $this->get(Branding::logoUrl())->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    // An old version still gets the current logo, only briefly cached.
    expect($this->get(route('branding.logo', ['version' => 'abc123']))->headers->get('Cache-Control'))->not->toContain('immutable');
});

it('replaces and removes the logo, deleting the old files', function () {
    uploadLogo(UploadedFile::fake()->image('first.png', 200, 80));
    $first = app(GeneralSettings::class)->logo_path;

    uploadLogo(UploadedFile::fake()->image('second.webp', 200, 80))->assertHasNoFormErrors();
    $second = app(GeneralSettings::class)->logo_path;

    expect($second)->not->toBe($first)->toEndWith('.webp');
    Storage::disk(Branding::DISK)->assertMissing($first);
    Storage::disk(Branding::DISK)->assertExists($second);

    Livewire::actingAs(superAdmin())->test(ManageGeneral::class)
        ->fillForm(['logo_path' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->logo_path)->toBeNull()
        ->and(Branding::logoUrl())->toBeNull();
    Storage::disk(Branding::DISK)->assertMissing($second);
    $this->get(route('branding.logo', ['version' => 'abc123']))->assertNotFound();
});

it('accepts a plain SVG and rejects SVGs that could run code or load content', function () {
    uploadLogo(svgLogo('<defs><linearGradient id="g"><stop offset="0" stop-color="#b50000"/></linearGradient></defs><rect width="10" height="10" fill="url(#g)"/>'))
        ->assertHasNoFormErrors();
    expect(app(GeneralSettings::class)->logo_path)->toEndWith('.svg');

    foreach ([
        '<script>alert(1)</script>',
        '<rect width="10" height="10" onload="alert(1)"/>',
        '<a href="javascript:alert(1)"><rect width="1" height="1"/></a>',
        '<image href="https://evil.example/x.png"/>',
        '<foreignObject><div>x</div></foreignObject>',
        '<style>@import url(https://evil.example/x.css);</style>',
    ] as $body) {
        uploadLogo(svgLogo($body))->assertHasFormErrors(['logo_path']);
    }

    uploadLogo(UploadedFile::fake()->createWithContent('logo.svg', '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'))
        ->assertHasFormErrors(['logo_path']);
});

it('rejects files that are not real images or are the wrong size', function () {
    uploadLogo(UploadedFile::fake()->createWithContent('logo.png', 'not really a png'))->assertHasFormErrors(['logo_path']);
    uploadLogo(UploadedFile::fake()->create('logo.gif', 10, 'image/gif'))->assertHasFormErrors(['logo_path']);
    uploadLogo(UploadedFile::fake()->image('tiny.png', 10, 10))->assertHasFormErrors(['logo_path']);
    uploadLogo(UploadedFile::fake()->image('huge.png', 400, 100)->size(2048))->assertHasFormErrors(['logo_path']);

    expect(app(GeneralSettings::class)->logo_path)->toBeNull();
});

it('lets only super-admins manage the logo', function () {
    $this->actingAs(User::factory()->create())->get(ManageGeneral::getUrl())->assertForbidden();
});
