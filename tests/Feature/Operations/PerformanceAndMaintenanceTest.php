<?php

use App\Domain\Identity\Models\User;
use App\Domain\Settings\PerformanceSettings;
use App\Filament\Pages\MaintenanceConsole;
use App\Filament\Pages\Settings\ManagePerformance;
use App\Support\Operations\MaintenanceCommands;
use App\Support\Redis\RedisRuntime;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** Nothing listens on port 1, so connecting fails at once. */
function unreachableRedis(array $overrides = []): PerformanceSettings
{
    $settings = app(PerformanceSettings::class);
    foreach ([
        'redis_enabled' => true,
        'redis_host' => '127.0.0.1',
        'redis_port' => 1,
        'redis_client' => 'predis',
        'cache_store' => 'redis',
        'queue_connection' => 'redis',
        'redis_fallback' => true,
        ...$overrides,
    ] as $key => $value) {
        $settings->{$key} = $value;
    }

    return $settings;
}

afterEach(function () {
    @unlink(storage_path(RedisRuntime::MARKER));
});

it('keeps running on the database when Redis is unreachable', function () {
    RedisRuntime::apply(unreachableRedis());

    expect(config('cache.default'))->toBe('database')
        ->and(config('queue.default'))->toBe('database')
        ->and(config('session.driver'))->toBe('database')
        ->and(config('session.connection'))->toBeNull()
        ->and(RedisRuntime::fallbackActive())->toBeTrue()
        ->and(RedisRuntime::idleQueues())->toBe([]);
});

it('uses Redis without probing when the fail-safe is off', function () {
    RedisRuntime::apply(unreachableRedis(['redis_fallback' => false]));

    expect(config('cache.default'))->toBe('redis')
        ->and(config('queue.default'))->toBe('redis')
        ->and(config('database.redis.cache.database'))->toBe(1)
        ->and(config('database.redis.options.prefix'))->toBe('sso:')
        ->and(RedisRuntime::idleQueues())->toBe(['database']);
});

it('ignores Redis stores while Redis is switched off', function () {
    RedisRuntime::apply(unreachableRedis(['redis_enabled' => false]));

    expect(config('cache.default'))->toBe('database')
        ->and(RedisRuntime::fallbackActive())->toBeFalse();
});

it('refuses to save Redis stores that cannot be reached', function () {
    $this->actingAs(superAdmin());

    Livewire::test(ManagePerformance::class)
        ->set('data.redis_enabled', true)
        ->set('data.redis_port', 1)
        ->set('data.cache_store', 'redis')
        ->call('save');

    expect(app(PerformanceSettings::class)->refresh()->cache_store)->toBe('database');
});

it('always keeps sessions in the database', function () {
    $this->actingAs(superAdmin());

    Livewire::test(ManagePerformance::class)
        ->set('data.session_store', 'redis')
        ->call('save')
        ->assertHasErrors(['data.session_store']);

    Livewire::test(ManagePerformance::class)
        ->set('data.cache_store', 'file')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(PerformanceSettings::class)->refresh();
    expect($settings->session_store)->toBe('database')
        ->and($settings->cache_store)->toBe('file');
});

it('reports an unreachable server', function () {
    $probe = RedisRuntime::probe(['redis_host' => '127.0.0.1', 'redis_port' => 1]);

    expect($probe['ok'])->toBeFalse()->and($probe['error'])->not->toBeEmpty();
});

it('keeps the maintenance console and Redis settings for super-admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/settings/maintenance')->assertForbidden();
    $this->get('/admin/settings/performance')->assertForbidden();

    $this->actingAs(superAdmin());
    $this->get('/admin/settings/maintenance')->assertOk()->assertSee('Clear application cache');
    $this->get('/admin/settings/performance')->assertOk()->assertSee('Where data is kept');
});

it('runs a predefined command and records it', function () {
    $this->actingAs($admin = superAdmin());

    Livewire::test(MaintenanceConsole::class)
        ->callAction('run', arguments: ['command' => 'settings-clear'])
        ->assertSet('result.exit_code', 0)
        ->assertSet('result.command', 'settings:clear-cache');

    $activity = Activity::query()->inLog(MaintenanceCommands::LOG)->latest('id')->first();
    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['command'])->toBe('settings:clear-cache');
});

it('runs nothing outside the predefined list', function () {
    $this->actingAs(superAdmin());

    Livewire::test(MaintenanceConsole::class)
        ->callAction('run', arguments: ['command' => 'migrate:fresh'])
        ->assertSet('result', null);

    expect(fn () => MaintenanceCommands::run('db:wipe', null))->toThrow(InvalidArgumentException::class);
});
