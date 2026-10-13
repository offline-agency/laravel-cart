<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests;

use Illuminate\Foundation\Application;
use OfflineAgency\LaravelCart\CartServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class FeatureTestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [CartServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cart.database.connection', 'testing');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', self::connectionConfig());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator): void {
            $migrator->path(realpath(__DIR__.'/../database/migrations'));
        });

        // A server database outlives the test, unlike SQLite :memory:, so start every test empty.
        if (self::usesServerDatabase()) {
            $this->artisan('db:wipe', ['--database' => 'testing']);
        }
    }

    /**
     * SQLite in memory by default; set CART_TEST_DB_DRIVER=pgsql (plus the
     * CART_TEST_DB_* variables) to run the suite against PostgreSQL.
     *
     * @return array<string, mixed>
     */
    private static function connectionConfig(): array
    {
        if (! self::usesServerDatabase()) {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => (string) getenv('CART_TEST_DB_DRIVER'),
            'host' => getenv('CART_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('CART_TEST_DB_PORT') ?: '5432',
            'database' => getenv('CART_TEST_DB_DATABASE') ?: 'laravel_cart_test',
            'username' => getenv('CART_TEST_DB_USERNAME') ?: 'postgres',
            'password' => getenv('CART_TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
    }

    private static function usesServerDatabase(): bool
    {
        return in_array(getenv('CART_TEST_DB_DRIVER'), ['pgsql'], true);
    }
}
