<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\RuleTransformers\EnumRule;
use Illuminate\Support\Arr;

class RuleTransformers
{
    protected array $transformers = [];

    protected array $appends = [];

    protected array $prepends = [];

    public function append(array|callable|string $transformers)
    {
        $this->appends = array_merge(
            $this->appends,
            Arr::wrap($transformers)
        );

        return $this;
    }

    public function prepend(array|callable|string $transformers)
    {
        $this->prepends = array_merge(
            $this->prepends,
            Arr::wrap($transformers)
        );

        return $this;
    }

    public function use(array $transformers)
    {
        $this->transformers = $transformers;

        return $this;
    }

    /**
     * @return (callable|class-string<RuleTransformer|AllRulesSchemasTransformer>)[]
     */
    public function all(): array
    {
        $base = $this->transformers ?: [
            EnumRule::class,
        ];

        return array_values(array_unique([
            ...$this->prepends,
            ...$base,
            ...$this->appends,
        ], SORT_REGULAR));
    }
}
