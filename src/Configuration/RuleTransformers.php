<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\RuleTransformers\ConfirmedRule;
use Dedoc\Scramble\RuleTransformers\EnumRule;
use Dedoc\Scramble\RuleTransformers\InRule;
use Dedoc\Scramble\Support\ContainerUtils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
     * @template TExtensionType of object
     *
     * @param  class-string<TExtensionType>  $type
     * @param  array<string, mixed>  $contextfulBindings
     * @return Collection<int, TExtensionType>
     */
    public function instances(string $type, array $contextfulBindings): Collection
    {
        return collect($this->all())
            ->filter(fn ($class) => is_a($class, $type, true))
            ->map(fn ($class) => ContainerUtils::makeContextable($class, $contextfulBindings))
            ->values();
    }

    /**
     * @return (callable|class-string<RuleTransformer|AllRulesSchemasTransformer>)[]
     */
    public function all(): array
    {
        $base = $this->transformers ?: [
            EnumRule::class,
            InRule::class,

            ConfirmedRule::class,
        ];

        return array_values(array_unique([
            ...$this->prepends,
            ...$base,
            ...$this->appends,
        ], SORT_REGULAR));
    }
}
