<?php

namespace HypathBel\ModelScribe;

use HypathBel\ModelScribe\Commands\MakeScribeTableCommand;
use HypathBel\ModelScribe\Commands\PruneLogsCommand;
use HypathBel\ModelScribe\Observers\ModelScribeObserver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModelScribeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('model-scribe')
            ->hasConfigFile()
            ->hasMigration('create_model_scribe_logs_table')
            ->hasCommands([
                PruneLogsCommand::class,
                MakeScribeTableCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register the DriverManager as a singleton so resolved drivers are cached.
        $this->app->singleton(DriverManager::class);

        // Register ModelScribeObserver — it depends on DriverManager.
        $this->app->singleton(ModelScribeObserver::class, function ($app) {
            return new ModelScribeObserver($app->make(DriverManager::class));
        });

        // Bind the main class.
        $this->app->singleton(ModelScribe::class, function ($app) {
            return new ModelScribe($app->make(DriverManager::class));
        });

        // Alias for the Facade.
        $this->app->alias(ModelScribe::class, 'model-scribe');
    }
}
