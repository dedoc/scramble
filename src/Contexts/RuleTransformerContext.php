<?php

namespace Dedoc\Scramble\Contexts;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\ContainerUtils;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\Collection;

class RuleTransformerContext
{
    /**
     * @param  Collection<int, Rule>  $fieldRules
     */
    public function __construct(
        public string $field,
        public Collection $fieldRules,
        public OpenApi $openApi,
        public GeneratorConfig $config,
    ) {}

    /**
     * @param  Collection<int, Rule>  $fieldRules
     */
    public function withFieldRules(Collection $fieldRules): static
    {
        $copy = clone $this;

        $copy->fieldRules = $fieldRules;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $bindings
     */
    public static function makeFromOpenApiContext(OpenApiContext $openApiContext, array $bindings = []): self
    {
        return ContainerUtils::makeContextable(RuleTransformerContext::class, [
            OpenApi::class => $openApiContext->openApi,
            GeneratorConfig::class => $openApiContext->config,
            ...$bindings,
        ]);
    }
}
