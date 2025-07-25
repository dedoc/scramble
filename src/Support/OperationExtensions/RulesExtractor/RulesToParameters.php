<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class RulesToParameters
{
    private bool $mergeDotNotatedKeys = true;

    /**
     * @param  array<string, RuleSet>  $rules
     * @param  array<string, PhpDocNode>  $rulesDocs
     */
    public function __construct(
        private array $rules,
        private TypeTransformer $openApiTransformer,
        private array $rulesDocs = [],
        private string $in = 'query',
    ) {}

    public function mergeDotNotatedKeys(bool $mergeDotNotatedKeys = true): self
    {
        $this->mergeDotNotatedKeys = $mergeDotNotatedKeys;

        return $this;
    }

    /**
     * @return Parameter[]
     */
    public function handle(): array
    {
        return collect($this->rules)
            ->pipe($this->handleConfirmed(...))
            ->map(fn ($rules, $name) => (new RulesToParameter($name, $rules, $this->rulesDocs[$name] ?? null, $this->openApiTransformer, $this->in))->generate())
            ->filter()
            ->values()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->all();
    }

    /**
     * @param  Collection<string, RuleSet>  $rules
     * @return Collection<string, RuleSet>
     */
    private function handleConfirmed(Collection $rules): Collection
    {
        $confirmedParamNameRules = $rules
            ->map(fn ($rules, $name) => [$name, Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules)])
            ->filter(fn ($nameRules) => in_array('confirmed', $nameRules[1], true));

        foreach ($confirmedParamNameRules as $confirmedParamNameRule) {
            $rules->offsetSet(
                "$confirmedParamNameRule[0]_confirmation",
                array_filter($confirmedParamNameRule[1], fn ($rule) => $rule !== 'confirmed'),
            );
        }

        return $rules;
    }
}
