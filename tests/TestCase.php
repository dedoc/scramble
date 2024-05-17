<?php

namespace Dedoc\Scramble\Tests;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->when(RulesToParameters::class)
            ->needs('$validationNodesResults')
            ->give([]);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dedoc\\Scramble\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Scramble::$defaultRoutesIgnored = false;
        Scramble::$routeResolver = null;
        Scramble::$openApiExtender = null;
        Scramble::$tagResolver = null;

        Context::reset();
    }

    protected function getPackageProviders($app)
    {
        return [
            ScrambleServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
