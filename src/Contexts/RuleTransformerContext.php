<?php

namespace Dedoc\Scramble\Contexts;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
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
     * @param  Collection<int, Rule>  $fieldRules
     */
    public function withFieldRules(Collection $fieldRules): static
    {
        $copy = clone $this;

        $copy->fieldRules = $fieldRules;

        return $this;
    }
}
