<?php

namespace Dedoc\Scramble\Tests;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route as RouteFacade;

it('preserves inferred query parameters across Octane request lifecycles', function () {
    RouteFacade::get('api/users', [RepeatedGenerationController::class, 'index']);
    Scramble::routes(fn (Route $route) => $route->uri === 'api/users');

    $base = $this->app;
    $documents = [];

    try {
        for ($generation = 1; $generation <= 3; $generation++) {
            // Octane clones the booted application, retaining singletons resolved at boot.
            $application = clone $base;
            // Octane also flushes scoped instances between request lifecycles.
            $application->forgetScopedInstances();

            Container::setInstance($application);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($application);

            $documents[$generation] = $application->make(Generator::class)();
        }
    } finally {
        Container::setInstance($base);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($base);
    }

    foreach ($documents as $generation => $document) {
        $parameters = array_filter(
            $document['paths']['/users']['get']['parameters'] ?? [],
            fn (array $parameter) => $parameter['in'] === 'query',
        );

        foreach (['search', 'status', 'per_page'] as $name) {
            $this->assertContains(
                $name,
                array_column($parameters, 'name'),
                "Generation {$generation} is missing query parameter [{$name}].",
            );
        }
    }
});

it('preserves inference configuration when scoped instances are flushed', function () {
    $original = Scramble::infer();
    $original->configure()->buildDefinitionsUsingReflectionFor([RepeatedGenerationController::class]);

    $this->app->forgetScopedInstances();

    $fresh = Scramble::infer();

    expect($fresh)->not->toBe($original)
        ->and($fresh->index)->not->toBe($original->index)
        ->and(app(Infer::class))->toBe($fresh)
        ->and($fresh->config)->toBe($original->config)
        ->and($fresh->config->shouldAnalyzeAst(Attribute::class))->toBeTrue()
        ->and($fresh->config->shouldAnalyzeAst(RepeatedGenerationController::class))->toBeFalse();
});

class RepeatedGenerationController
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->input('status');

        return RepeatedGenerationUserResource::collection(
            SampleUserModel::query()
                ->where('name', $search)
                ->where('status', $status)
                ->paginate($request->integer('per_page', 15))
        );
    }
}

/**
 * @mixin SampleUserModel
 */
class RepeatedGenerationUserResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['id' => $this->id];
    }
}
