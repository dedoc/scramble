<?php

namespace Dedoc\Scramble\Tests;

use Dedoc\Scramble\Exceptions\InvalidSchema;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\DefineRoute;

/**
 * This is not a Pest test, as `DefineEnvironment` attribute cannot be used in Pest.
 */
class ScrambleTest extends TestCase
{
    #[DefineEnvironment('withEnforcedUnknownSchemaPrevention')]
    /** @test */
    public function throws_when_unknown_type_is_prevented()
    {
        expect(fn () => $this->generateForRoute(fn () => Route::get('test', [ScrambleSchemaRulesTest_Controller::class, 'test'])))
            ->toThrow(fn (InvalidSchema $e) => expect($e->getMessage())->toContain(
                'GET test',
                'Dedoc\Scramble\Tests\ScrambleSchemaRulesTest_Controller@test',
                '/paths/test/get/responses/0/content/application~1json/type',
                '[Dedoc\Scramble\Tests\ScrambleSchemaRulesTest_Controller] on line',
            ));
    }

    #[DefineEnvironment('withClosureAllRouteResolver')]
    /** @test */
    public function caches_routes_when_closure_resolver_set()
    {
        $this->assertCount(2, $routes = $this->getScrambleRoutes());

        $this->assertRoutesAreCacheable($routes);
    }

    /** @test */
    public function caches_default_routes()
    {
        $this->assertCount(2, $routes = $this->getScrambleRoutes());

        $this->assertRoutesAreCacheable($routes);
    }

    /** @test */
    public function registers_default_api()
    {
        expect(Scramble::getGeneratorConfig('default'))->toBeTruthy();
    }

    /** @test */
    public function registers_routes_for_default_api()
    {
        expect($routes = $this->getScrambleRoutes())
            ->toHaveCount(2)
            ->and($routes[0]->uri)->toBe('docs/api')
            ->and($routes[0]->methods)->toBe(['GET', 'HEAD'])
            ->and($routes[1]->uri)->toBe('docs/api.json')
            ->and($routes[1]->methods)->toBe(['GET', 'HEAD']);
    }

    /** @test */
    #[DefineEnvironment('registerCustomPathApi')]
    #[DefineRoute('registerCustomNewsletterApiRoutes')]
    public function generates_correct_server_url_when_api_config_defines_custom_api_path()
    {
        $generator = app(Generator::class);

        $doc = $generator(Scramble::getGeneratorConfig('newsletter'));

        $this->assertEquals('http://localhost/newsletter/api', $doc['servers'][0]['url']);
        $this->assertEquals(['/a'], array_keys($doc['paths']));
    }

    /** @test */
    #[DefineRoute('registerTestConsumerRoutes')]
    public function filters_consumer_routes_with_config_file()
    {
        $generator = app(Generator::class);

        $doc = $generator(Scramble::getGeneratorConfig('default'));

        $this->assertEquals('http://localhost/api', $doc['servers'][0]['url']);
        $this->assertEquals(['/a', '/b', '/c'], array_keys($doc['paths']));
    }

    /** @test */
    #[DefineRoute('registerTestConsumerRoutes')]
    #[DefineEnvironment('withEmptyConfigApiPath')]
    #[DefineEnvironment('withClosureAllRouteResolver')]
    public function filters_consumer_routes_with_redefined_resolver_and_api_path_config_file()
    {
        $generator = app(Generator::class);

        $doc = $generator(Scramble::getGeneratorConfig('default'));

        $this->assertEquals('http://localhost', $doc['servers'][0]['url']);
        $this->assertTrue(collect(['/api/a', '/api/b', '/api/c', '/second-api/a', '/second-api/b', '/second-api/c'])->every(fn ($r) => in_array($r, array_keys($doc['paths']))));
    }

    protected function registerCustomPathApi()
    {
        Scramble::ignoreDefaultRoutes();

        Scramble::registerApi('newsletter', [
            'api_path' => 'newsletter/api',
        ]);
    }

    protected function registerCustomNewsletterApiRoutes(Router $router)
    {
        $router->group(['prefix' => 'newsletter/api'], function (Router $router) {
            $router->get('a', [ScrambleTest_Controller::class, 'test']);
        });
    }

    protected function withEnforcedUnknownSchemaPrevention()
    {
        Scramble::preventSchema(UnknownType::class);
    }

    protected function withClosureAllRouteResolver()
    {
        Scramble::routes(fn () => true);
    }

    protected function withEmptyConfigApiPath($app)
    {
        $app['config']->set('scramble.api_path', '');
    }

    protected function registerTestConsumerRoutes(Router $router)
    {
        $router->group(['prefix' => 'api'], function (Router $router) {
            $router->get('a', [ScrambleTest_Controller::class, 'test']);
            $router->get('b', [ScrambleTest_Controller::class, 'test']);
            $router->get('c', [ScrambleTest_Controller::class, 'test']);
        });

        $router->group(['prefix' => 'second-api'], function (Router $router) {
            $router->get('a', [ScrambleTest_Controller::class, 'test']);
            $router->get('b', [ScrambleTest_Controller::class, 'test']);
            $router->get('c', [ScrambleTest_Controller::class, 'test']);
        });
    }

    private function assertRoutesAreCacheable($routes)
    {
        foreach ($routes as $route) {
            $route->prepareForSerialization();
        }

        unserialize(serialize($routes));

        expect(true)->toBeTrue();
    }
}

class ScrambleTest_Controller
{
    public function test() {}
}

class ScrambleSchemaRulesTest_Controller
{
    public function test()
    {
        return some_function();
    }
}
