<?php

namespace Dedoc\Scramble\Tests;

use Attribute;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Contracts\Attributes\Invokable;
use Orchestra\Testbench\Features\TestingFeature;

class GeneratorConfigTest extends TestCase
{
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        TestingFeature::run(
            testCase: $this,
            attribute: fn () => $this->parseTestMethodAttributes($app, WithProviders::class), /** @phpstan-ignore method.notFound */
        );
    }

    /** @test */
    public function registers_default_routes()
    {
        /** @var RouteCollectionInterface $routes */
        $routes = app()->get(Router::class)->getRoutes();

        $this->assertCount(2, $routes->getRoutes());

        $this->assertNotNull($routes->getByName('scramble.docs.ui'));
        $this->assertNotNull($routes->getByName('scramble.docs.document'));
    }

    /** @test */
    #[WithProviders([DisablesExposedRoutes_GeneratorConfigTest::class])]
    public function allows_disabling_routes_with_expose()
    {
        /** @var RouteCollectionInterface $routes */
        $routes = app()->get(Router::class)->getRoutes();

        $this->assertCount(0, $routes->getRoutes());
    }

    /** @test */
    #[WithProviders([RegistersCustomRoutesForDefault_GeneratorConfigTest::class])]
    public function allows_exposing_default_documentation_with_custom_routes()
    {
        /** @var RouteCollectionInterface $routes */
        $routes = app()->get(Router::class)->getRoutes();

        $this->assertCount(2, $routes->getRoutes());

        /** @var Route|null $uiRoute */
        $uiRoute = collect($routes->getRoutes())->firstWhere('uri', 'documentation');

        $this->assertNotNull($uiRoute);
        $this->assertEquals('GET', $uiRoute->methods()[0]);

        /** @var Route|null $documentRoute */
        $documentRoute = collect($routes->getRoutes())->firstWhere('uri', 'openapi.json');

        $this->assertNotNull($documentRoute);
        $this->assertEquals('GET', $documentRoute->methods()[0]);
    }

    /** @test */
    #[WithProviders([RegistersNotExposedApi_GeneratorConfigTest::class])]
    public function registered_api_isnt_exposed_by_default()
    {
        /** @var RouteCollectionInterface $routes */
        $routes = app()->get(Router::class)->getRoutes();

        $this->assertCount(0, $routes->getRoutes());
    }

    /** @test */
    #[WithProviders([RegistersExposedApi_GeneratorConfigTest::class])]
    public function registered_api_exposed_explicitly()
    {
        /** @var RouteCollectionInterface $routes */
        $routes = app()->get(Router::class)->getRoutes();

        $this->assertCount(2, $routes->getRoutes());
        $this->assertNotNull(
            collect($routes->getRoutes())->firstWhere('uri', 'docs/v2')
        );
        $this->assertNotNull(
            collect($routes->getRoutes())->firstWhere('uri', 'docs/v2/openapi.json')
        );
    }
}

class DisablesExposedRoutes_GeneratorConfigTest extends ServiceProvider
{
    public function boot()
    {
        Scramble::configure()->expose(false);
    }
}

class RegistersCustomRoutesForDefault_GeneratorConfigTest extends ServiceProvider
{
    public function boot()
    {
        Scramble::configure()
            ->expose(
                ui: 'documentation',
                document: 'openapi.json',
            );
    }
}

class RegistersNotExposedApi_GeneratorConfigTest extends ServiceProvider
{
    public function boot()
    {
        Scramble::configure()->expose(false);

        Scramble::registerApi('v2');
    }
}

class RegistersExposedApi_GeneratorConfigTest extends ServiceProvider
{
    public function boot()
    {
        Scramble::configure()->expose(false);

        Scramble::registerApi('v2')->expose(
            ui: 'docs/v2',
            document: 'docs/v2/openapi.json',
        );
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class WithProviders implements Invokable
{
    public function __construct(
        public readonly array $prependedProviders = [],
    ) {}

    public function __invoke($app)
    {
        $app['config']->set('app.providers', [
            ...array_values(array_filter($app['config']->get('app.providers'), function ($p) {
                return $p !== ScrambleServiceProvider::class;
            })),
            ...$this->prependedProviders,
            ScrambleServiceProvider::class,
        ]);
    }
}
