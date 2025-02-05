<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Router;
use LogicException;

class GeneratorConfigCollection
{
    /**
     * @var array<string, GeneratorConfig>
     */
    private array $apis = [];

    public function __construct()
    {
        $this->apis[Scramble::DEFAULT_API] = $this->buildDefaultApiConfiguration();
    }

    private function buildDefaultApiConfiguration(): GeneratorConfig
    {
        return (new GeneratorConfig)
            ->expose(
                ui: fn (Router $router, $action) => $router->get('docs/api', $action)->name('scramble.docs.ui'),
                document: fn (Router $router, $action) => $router->get('docs/api.json', $action)->name('scramble.docs.document'),
            );
    }

    public function get(string $name): GeneratorConfig
    {
        if (! array_key_exists($name, $this->apis)) {
            throw new LogicException("$name API is not registered. Register the API using `Scramble::registerApi` first.");
        }

        return $this->apis[$name];
    }

    public function register(string $name, array $config): GeneratorConfig
    {
        $this->apis[$name] = $generatorConfig = new GeneratorConfig(
            config: array_merge(config('scramble') ?: [], $config),
            parametersExtractors: clone $this->apis[Scramble::DEFAULT_API]->parametersExtractors,
            operationTransformers: clone $this->apis[Scramble::DEFAULT_API]->operationTransformers,
            documentTransformers: clone $this->apis[Scramble::DEFAULT_API]->documentTransformers,
            serverVariables: clone $this->apis[Scramble::DEFAULT_API]->serverVariables,
        );

        return $generatorConfig;
    }

    public function all(): array
    {
        return $this->apis;
    }
}
