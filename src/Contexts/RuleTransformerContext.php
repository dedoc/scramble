<?php

namespace Dedoc\Scramble\Contexts;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Illuminate\Support\Collection;

class RuleTransformerContext
{
    public function __construct(
        public string $field,
        public Collection $fieldRules,
        public OpenApi $openApi,
        public GeneratorConfig $config,
    ) {}

    /**
     * @param Collection<int, Rule> $fieldRules
     *
     * @return static
     */
    public function withFieldRules(Collection $fieldRules): static
    {
        $copy = clone $this;

        $copy->fieldRules = $fieldRules;

        return $this;
    }
}
