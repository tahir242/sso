<?php

use App\Domain\Identity\Models\User;
use App\Filament\Pages\SystemInformation as SystemInformationPage;
use App\Support\Operations\SystemInformation;
use Livewire\Livewire;

it('shows system information to super-admins only', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/admin/settings/system')->assertForbidden();

    $this->actingAs(superAdmin());
    $this->get('/admin/settings/system')
        ->assertOk()
        ->assertSee('System information &amp; health', false)
        ->assertSee(PHP_VERSION)
        ->assertSee(app()->version())
        ->assertSee('Health checks')
        ->assertSee('Cache / sessions / queue');
});

it('refreshes without errors', function () {
    $this->actingAs(superAdmin());

    Livewire::test(SystemInformationPage::class)->callAction('refresh')->assertOk();
});

it('reports server, PHP, application, database, background and storage facts', function () {
    $sections = app(SystemInformation::class)->sections();

    expect(array_keys($sections))->toBe(['Server', 'PHP', 'Application', 'Database', 'Cache, sessions & queue', 'Storage']);

    $rows = collect($sections)->flatten(1);
    expect($rows->firstWhere('label', 'Connection')['value'])->toStartWith('Connected')
        ->and($rows->firstWhere('label', 'Laravel')['value'])->toBe(app()->version())
        ->and($rows->firstWhere('label', 'Extensions')['value'])->toContain('sodium');

    // A hint is shown only when something needs attention.
    $rows->each(fn (array $row) => expect(isset($row['hint']))->toBe(isset($row['status']) && $row['status'] !== 'ok'));
});

it('never shows secrets', function () {
    config(['database.connections.sqlite.password' => 'db-secret-value', 'app.key' => 'base64:app-secret-value']);

    $text = json_encode(app(SystemInformation::class)->sections());

    expect($text)->not->toContain('db-secret-value')->not->toContain('app-secret-value');
});

it('reads the installed version from RELEASE.txt, else the changelog', function () {
    expect(SystemInformation::version())->toMatch('/^v\d+\.\d+\.\d+/');
});
