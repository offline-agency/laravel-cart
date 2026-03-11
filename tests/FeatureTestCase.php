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
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator): void {
            $migrator->path(realpath(__DIR__.'/../database/migrations'));
        });
    }
}
