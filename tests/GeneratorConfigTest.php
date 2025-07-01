<?php

namespace Dedoc\Scramble\Tests;

use Attribute;
use Dedoc\Scramble\DocumentTransformers\AddDocumentTags;
use Dedoc\Scramble\DocumentTransformers\CleanupUnusedResponseReferencesTransformer;
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
    protected function setUp(): void
    {
        if (! class_exists(TestingFeature::class)) {
            $this->markTestSkipped('TestingFeature class does not exist');
        }

        parent::setUp();
    }

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

        $this->assertCount(2, $this->getScrambleRoutes());

        $this->assertNotNull($routes->getByName('scramble.docs.ui'));
        $this->assertNotNull($routes->getByName('scramble.docs.document'));
    }

    /** @test */
    #[WithProviders([DisablesExposedRoutes_GeneratorConfigTest::class])]
    public function allows_disabling_routes_with_expose()
    {
        $routes = $this->getScrambleRoutes();

        $this->assertCount(0, $routes);
    }

    /** @test */
    #[WithProviders([RegistersCustomRoutesForDefault_GeneratorConfigTest::class])]
    public function allows_exposing_default_documentation_with_custom_routes()
    {
        $routes = $this->getScrambleRoutes();

        $this->assertCount(2, $routes);

        /** @var Route|null $uiRoute */
        $uiRoute = collect($routes)->firstWhere('uri', 'documentation');

        $this->assertNotNull($uiRoute);
        $this->assertEquals('GET', $uiRoute->methods()[0]);

        /** @var Route|null $documentRoute */
        $documentRoute = collect($routes)->firstWhere('uri', 'openapi.json');

        $this->assertNotNull($documentRoute);
        $this->assertEquals('GET', $documentRoute->methods()[0]);
    }

    /** @test */
    #[WithProviders([RegistersNotExposedApi_GeneratorConfigTest::class])]
    public function registered_api_isnt_exposed_by_default()
    {
        $this->assertCount(0, $this->getScrambleRoutes());
    }

    /** @test */
    #[WithProviders([RegistersExposedApi_GeneratorConfigTest::class])]
    public function registered_api_exposed_explicitly()
    {
        $routes = $this->getScrambleRoutes();

        $this->assertCount(2, $routes);
        $this->assertNotNull(
            collect($routes)->firstWhere('uri', 'docs/v2')
        );
        $this->assertNotNull(
            collect($routes)->firstWhere('uri', 'docs/v2/openapi.json')
        );
    }

    /** @test */
    #[WithProviders([UsesCommonConfiguration_GeneratorConfigTest::class])]
    public function uses_common_configurations()
    {
        $defaultConfig = Scramble::getConfigurationsInstance()->get('default');
        $v2Config = Scramble::getConfigurationsInstance()->get('v2');

        $this->assertEquals(['common', 'only-default', AddDocumentTags::class, CleanupUnusedResponseReferencesTransformer::class], $defaultConfig->documentTransformers->all());
        $this->assertEquals(['common', 'only-v2'], $v2Config->documentTransformers->all());
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

class UsesCommonConfiguration_GeneratorConfigTest extends ServiceProvider
{
    public function boot()
    {
        Scramble::configure()->withDocumentTransformers('common');

        Scramble::registerApi('v2')->withDocumentTransformers('only-v2');

        Scramble::configure()->withDocumentTransformers('only-default');
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
