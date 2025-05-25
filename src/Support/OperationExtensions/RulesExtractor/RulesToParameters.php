<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\RulesNodes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * @phpstan-type Rules string|ValidationRule|(string|ValidationRule)[]
 */
class RulesToParameters
{
    /** @var array<string, PhpDocNode> */
    private array $nodeDocs;

    private bool $mergeDotNotatedKeys = true;

    /**
     * @param  array<string, Rules>  $rules
     */
    public function __construct(
        private array $rules,
        RulesNodes $validationNodesResults,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {
        $this->nodeDocs = $validationNodesResults->getDocNodes();
    }

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
            ->map(fn ($rules, $name) => (new RulesToParameter($name, $rules, $this->nodeDocs[$name] ?? null, $this->openApiTransformer, $this->in))->generate())
            ->filter()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, Rules>  $rules
     * @return Collection<string, Rules>
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
