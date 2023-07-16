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

        Scramble::$openApiExtender = null;

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

        //        $migration = include __DIR__.'/migrations/create_documentor_table.php.stub';
        //        $migration->up();
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
