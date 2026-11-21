<?php

use App\Domain\Identity\Models\User;
use App\Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// SSO has no public homepage: the sign-in screen is the entry point.

it('sends visitors to the sign-in screen', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk()->assertSee('Sign in');
});

it('sends signed-in users to the console or the portal by their roles', function () {
    $this->actingAs(superAdmin())->get('/')->assertRedirect(url('admin'));
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('home'));
});

it('has no portfolio or dashboard pages, routes, tables or menu items any more', function () {
    foreach (['/apps/hr', '/apps/hr/dashboards', '/platform', '/new-staff', '/apps/hr/suggest'] as $url) {
        $this->get($url)->assertNotFound();
    }

    $names = collect(Route::getRoutes()->getRoutesByName())->keys();
    expect($names->filter(fn (string $n) => str_starts_with($n, 'portfolio') || str_contains($n, 'portfolio')))->toBeEmpty();

    foreach (['portfolio_entries', 'release_notes', 'application_dashboards', 'application_dashboard_role'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    expect(Schema::hasColumn('applications', 'is_platform'))->toBeFalse();

    $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertDontSee('Software portfolio');
    $this->actingAs(superAdmin())->get(Dashboard::getUrl())->assertOk()
        ->assertDontSee('Software portfolio')->assertDontSee('Application portfolios');
});

it('keeps the application when going from IT help to a suggestion', function () {
    $app = makeApp('HR');
    $this->actingAs(User::factory()->create());

    $this->get('/support/new?app=hr')->assertOk()->assertSee(route('suggestions.new', ['app' => 'hr']), false);
    $this->get('/suggest')->assertOk()->assertSee('Suggest an improvement');
});
