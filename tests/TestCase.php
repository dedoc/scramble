<?php

namespace Dedoc\Scramble\Tests;

use Closure;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Routing\Route;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Scramble::throwOnError();

        $this->app->when(RulesToParameters::class)
            ->needs('$validationNodesResults')
            ->give([]);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dedoc\\Scramble\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getScrambleRoutes()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutes();

        return array_values(array_filter(
            $routes,
            fn ($r) => ! $r->named('storage.local'),
        ));
    }

    protected function tearDown(): void
    {
        Context::reset();

        Scramble::$tagResolver = null;
        Scramble::$enforceSchemaRules = [];
        Scramble::$defaultRoutesIgnored = false;
        Scramble::$extensions = [];

        parent::tearDown();
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

    public function generateForRoute(Closure $param)
    {
        $route = $param();

        Scramble::routes(fn (Route $r) => $r->uri === $route->uri);

        return app()->make(\Dedoc\Scramble\Generator::class)();
    }
}
