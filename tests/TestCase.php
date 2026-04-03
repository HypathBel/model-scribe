<?php

namespace HypathBel\ModelScribe\Tests;

use HypathBel\ModelScribe\ModelScribeServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'HypathBel\\ModelScribe\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ModelScribeServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // Run the package migration so tests have the table.
        $migration = include __DIR__.'/../database/migrations/create_model_scribe_logs_table.php.stub';
        $migration->up();
    }
}
