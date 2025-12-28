<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\RuleTransformers\ConfirmedRule;
use Dedoc\Scramble\RuleTransformers\EnumRule;
use Dedoc\Scramble\RuleTransformers\ExistsRule;
use Dedoc\Scramble\RuleTransformers\InRule;
use Dedoc\Scramble\RuleTransformers\RegexRule;
use Dedoc\Scramble\Support\ContainerUtils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class RuleTransformers
{
    /** @var list<class-string<RuleTransformer|AllRulesSchemasTransformer>> */
    protected array $transformers = [];

    /** @var list<class-string<RuleTransformer|AllRulesSchemasTransformer>> */
    protected array $appends = [];

    /** @var list<class-string<RuleTransformer|AllRulesSchemasTransformer>> */
    protected array $prepends = [];

    /**
     * @param  class-string<RuleTransformer|AllRulesSchemasTransformer>|list<class-string<RuleTransformer|AllRulesSchemasTransformer>>  $transformers
     */
    public function append(array|string $transformers): self
    {
        $this->appends = array_values(array_merge(
            $this->appends,
            Arr::wrap($transformers)
        ));

        return $this;
    }

    /**
     * @param  class-string<RuleTransformer|AllRulesSchemasTransformer>|list<class-string<RuleTransformer|AllRulesSchemasTransformer>>  $transformers
     */
    public function prepend(array|string $transformers): self
    {
        $this->prepends = array_values(array_merge(
            $this->prepends,
            Arr::wrap($transformers)
        ));

        return $this;
    }

    /**
     * @param  list<class-string<RuleTransformer|AllRulesSchemasTransformer>>  $transformers
     */
    public function use(array $transformers): self
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
        return collect($this->all()) // @phpstan-ignore return.type
            ->filter(fn ($class) => is_a($class, $type, true))
            ->map(fn ($class) => ContainerUtils::makeContextable($class, $contextfulBindings))
            ->values();
    }

    /**
     * @return list<class-string<RuleTransformer|AllRulesSchemasTransformer>>
     */
    public function all(): array
    {
        $base = $this->transformers ?: [
            EnumRule::class,
            InRule::class,
            ConfirmedRule::class,
            ExistsRule::class,
            RegexRule::class,
        ];

        return array_values(array_unique([
            ...$this->prepends,
            ...$base,
            ...$this->appends,
        ], SORT_REGULAR));
    }
}
