<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class RulesToParameters
{
    /** @var array<string, PhpDocNode> */
    private array $nodeDocs;

    private bool $mergeDotNotatedKeys = true;

    public function __construct(
        private array $rules,
        array $validationNodesResults,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {
        $this->nodeDocs = $this->extractNodeDocs($validationNodesResults);
    }

    public function mergeDotNotatedKeys(bool $mergeDotNotatedKeys = true)
    {
        $this->mergeDotNotatedKeys = $mergeDotNotatedKeys;

        return $this;
    }

    public function handle()
    {
        return collect($this->rules)
            ->pipe($this->handleConfirmed(...))
            ->map(fn ($rules, $name) => (new RulesToParameter($name, $rules, $this->nodeDocs[$name] ?? null, $this->openApiTransformer, $this->in))->generate())
            ->filter()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->values()
            ->all();
    }

    private function handleConfirmed(Collection $rules)
    {
        $confirmedParamNameRules = $rules
            ->map(fn ($rules, $name) => [$name, Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules)])
            ->filter(fn ($nameRules) => in_array('confirmed', $nameRules[1]));

        if (! $confirmedParamNameRules) {
            return $rules;
        }

        foreach ($confirmedParamNameRules as $confirmedParamNameRule) {
            $rules->offsetSet(
                "$confirmedParamNameRule[0]_confirmation",
                array_filter($confirmedParamNameRule[1], fn ($rule) => $rule !== 'confirmed'),
            );
        }

        return $rules;
    }

    private function extractNodeDocs($validationNodesResults)
    {
        return collect($validationNodesResults)
            ->mapWithKeys(fn (Node\Expr\ArrayItem $item) => [
                $item->key->value => $item->getAttribute('parsedPhpDoc'),
            ])
            ->toArray();
    }
}
