<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Tests rebuild their database (migrate:fresh). Stop before that if the
        // configuration points anywhere but a throw-away test database — for
        // example a server's cached config, which ignores phpunit.xml.
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($app->configurationIsCached()
            || ! ($database === ':memory:' || str_ends_with(strtolower($database), '_test'))) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$connection}: {$database}]. Tests only use an in-memory SQLite "
                .'database or one whose name ends in _test. Check for bootstrap/cache/config.php and the DB_* variables.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never call real servers (e.g. back-channel logout URLs).
        Http::preventStrayRequests();

        // OIDC tests sign tokens with the Passport key pair.
        if (! is_file(Passport::keyPath('oauth-private.key'))) {
            Artisan::call('passport:keys', ['--no-interaction' => true]);
        }
    }
}
