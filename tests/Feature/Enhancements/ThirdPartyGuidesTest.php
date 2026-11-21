<?php

use App\Domain\Federation\Oidc\Issuer;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use Livewire\Livewire;

it('keeps third-party integration guides in the developer docs', function () {
    $this->get('/integrations')->assertNotFound();

    // Same audience as the other integration guides.
    $this->get('/docs/integration/moodle')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/docs/integration/moodle')->assertForbidden();

    $this->actingAs(superAdmin())->get('/docs/integration/php')->assertOk()
        ->assertSee('Third-party software')->assertSee('Moodle')->assertSee('Koha')->assertSee('Nextcloud')->assertSee('Grafana');

    foreach (['moodle', 'koha', 'nextcloud', 'grafana'] as $page) {
        $this->get("/docs/integration/{$page}")->assertOk()->assertSee('How sign-in works')->assertDontSee('{{');
    }

    $this->get('/docs/integration/moodle')
        ->assertSee('/admin/oauth2callback.php')
        ->assertSee(rtrim(Issuer::url(), '/').'/.well-known/openid-configuration');
    $this->get('/docs/integration/not-a-guide')->assertNotFound();
});

it('keeps the users table usable without sideways scrolling', function () {
    $this->actingAs(superAdmin());

    $table = Livewire::test(ListUsers::class)->instance()->getTable();
    expect($table->isStackedOnMobile())->toBeTrue()
        ->and($table->getColumn('email')->getVisibleFrom())->toBe('xl')
        ->and($table->getColumn('username'))->toBeNull();
});
