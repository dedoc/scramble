<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QueryParametersConverter
{
    /**
     * @param  Collection<int, Parameter>  $parameters
     */
    public function __construct(private Collection $parameters) {}

    /**
     * @return Parameter[]
     */
    public function handle(): array
    {
        $paramsByName = $this->parameters->keyBy(fn ($p) => $p->name);

        /*
         * Rejecting array "container" parameters for cases when there are properties specified. For example:
         * ['filter' => 'array', 'filter.accountable' => 'integer']
         * In this ruleset `filter` should not be documented at all as the accountable is enough.
         */
        $parameters = $this->parameters->reject(fn (Parameter $p) => $paramsByName->keys()->some(fn (string $key) => Str::startsWith($key, $p->name.'.')));

        [$parametersToAdapt, $otherParameters] = $parameters->partition($this->shouldAdaptParameterForQuery(...))->all();

        return $parametersToAdapt
            ->map(fn ($p) => $this->convertParameterToQueryAdapted($p, $paramsByName))
            ->merge(array_map(
                $this->maybeMarkDeepParameter(...),
                $this->convertDotNamedParamsToComplexStructures($otherParameters),
            ))
            ->filter()
            ->values()
            ->all();
    }

    private function shouldAdaptParameterForQuery(Parameter $parameter): bool
    {
        if ($parameter->getAttribute('isFlat')) {
            return false;
        }

        $isScalar = ! in_array($parameter->schema->type->type ?? null, ['array', 'object', null], strict: true);

        $isArrayOfScalar = ($parameter->schema->type ?? null) instanceof ArrayType
            && ! in_array($parameter->schema->type->items->type ?? null, ['array', 'object', null], strict: true);

        if (! Str::contains($parameter->name, '*')) { // no nested arrays
            return $isScalar || $isArrayOfScalar;
        }

        if (Str::endsWith($parameter->name, '*') && (Str::substrCount($parameter->name, '*') === 1)) {
            return $isScalar;
        }

        return false;
    }

    /**
     * @param  Collection<string, Parameter>  $paramsByName
     */
    private function convertParameterToQueryAdapted(Parameter $originalParameter, Collection $paramsByName): ?Parameter
    {
        $parameter = clone $originalParameter;

        $parameter->name = Str::of($parameter->name)
            ->explode('.')
            ->map(fn ($str, $i) => $i === 0 ? $str : ($str === '*' ? '[]' : "[$str]"))
            ->join('');

        if ($parameter->schema?->type instanceof ArrayType) {
            $parameter->name .= '[]';
        }

        if (
            $parameter->name !== $originalParameter->name
            && ($sameNameParam = $paramsByName->get($parameter->name))
            && $sameNameParam !== $originalParameter
        ) {
            return null;
        }

        if (Str::endsWith($parameter->name, '[]') && $parameter->schema && ! $parameter->schema->type instanceof ArrayType) {
            $parameter->schema->type = (new ArrayType)
                ->setItems($parameter->schema->type)
                ->addProperties($parameter->schema->type);
        }

        return $parameter;
    }

    private function maybeMarkDeepParameter(Parameter $parameter): Parameter
    {
        $isDeep = in_array($parameter->schema?->type->type, ['array', 'object', null], strict: true);

        if ($isDeep) {
            $parameter->setExtensionProperty('deepObject-style', 'qs');
        }

        return $parameter;
    }

    /**
     * @param  Collection<int, Parameter>  $params
     * @return Parameter[]
     */
    private function convertDotNamedParamsToComplexStructures(Collection $params): array
    {
        return (new DeepParametersMerger($params))->handle();
    }
}
