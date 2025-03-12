<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeepParametersMerger
{
    public function __construct(private Collection $parameters) {}

    public function handle()
    {
        return $this->parameters->groupBy('in')
            ->map(fn ($parameters) => $this->handleNested($parameters->keyBy('name'))->values())
            ->flatten()
            ->values()
            ->all();
    }

    private function handleNested(Collection $parameters)
    {
        [$forcedFlatParameters, $maybeDeepParameters] = $parameters->partition(fn (Parameter $p) => $p->getAttribute('isFlat') === true);

        [$nested, $parameters] = $maybeDeepParameters
            ->sortBy(fn ($_, $key) => count(explode('.', $key)))
            ->partition(fn ($_, $key) => Str::contains($key, '.'));

        $nestedParentsKeys = $nested->keys()->map(fn ($key) => explode('.', $key)[0]);

        [$nestedParents, $parameters] = $parameters->partition(fn ($_, $key) => $nestedParentsKeys->contains($key));

        /** @var Collection $nested */
        $nested = $nested->merge($nestedParents);

        $nested = $nested
            ->groupBy(fn ($_, $key) => explode('.', $key)[0])
            ->map(function (Collection $params, $groupName) {
                $params = $params->keyBy('name');

                $baseParam = $params->get(
                    $groupName,
                    Parameter::make($groupName, $params->first()->in)
                        ->setSchema(Schema::fromType(new ObjectType))
                );

                $params->offsetUnset($groupName);

                foreach ($params as $param) {
                    $this->setDeepType(
                        $baseParam->schema->type,
                        $param->name,
                        $param,
                    );
                }

                return $baseParam;
            });

        return $parameters
            ->merge($forcedFlatParameters)
            ->merge($nested);
    }

    private function setDeepType(Type &$base, string $key, Parameter $parameter)
    {
        $typeToSet = $this->extractTypeFromParameter($parameter);

        $containingType = $this->getOrCreateDeepTypeContainer(
            $base,
            (explode('.', $key)[0] ?? '') === '*'
                ? explode('.', $key)
                : collect(explode('.', $key))
                    ->splice(1)
                    ->values()
                    ->all(),
        );

        if (! $containingType) {
            return;
        }

        $isSettingArrayItems = ($settingKey = collect(explode('.', $key))->last()) === '*';

        if ($containingType === $base && $base instanceof UnknownType) {
            $containingType = ($isSettingArrayItems ? new ArrayType : new ObjectType)
                ->addProperties($base);

            $base = $containingType;
        }

        if (! ($containingType instanceof ArrayType || $containingType instanceof ObjectType)) {
            return;
        }

        if ($isSettingArrayItems && $containingType instanceof ArrayType) {
            $containingType->items = $typeToSet;

            return;
        }

        if (! $isSettingArrayItems && $containingType instanceof ObjectType) {
            $containingType
                ->addProperty($settingKey, $typeToSet)
                ->addRequired($parameter->required ? [$settingKey] : []);
        }
    }

    private function getOrCreateDeepTypeContainer(Type &$base, array $path)
    {
        $key = $path[0];

        if (count($path) === 1) {
            if ($key !== '*' && $base instanceof ArrayType) {
                $base = new ObjectType;
            }

            return $base;
        }

        if ($key === '*') {
            if (! $base instanceof ArrayType) {
                $base = new ArrayType;
            }

            $next = $path[1];
            if ($next === '*') {
                if (! $base->items instanceof ArrayType) {
                    $base->items = new ArrayType;
                }
            } else {
                if (! $base->items instanceof ObjectType) {
                    $base->items = new ObjectType;
                }
            }

            return $this->getOrCreateDeepTypeContainer(
                $base->items,
                collect($path)->splice(1)->values()->all(),
            );
        } else {
            if (! $base instanceof ObjectType) {
                $base = new ObjectType;
            }

            $next = $path[1];

            if (! $base->hasProperty($key)) {
                $base = $base->addProperty(
                    $key,
                    $next === '*' ? new ArrayType : new ObjectType,
                );
            }
            if (($existingType = $base->getProperty($key)) instanceof UnknownType) {
                $base = $base->addProperty(
                    $key,
                    ($next === '*' ? new ArrayType : new ObjectType)->addProperties($existingType),
                );
            }

            if ($next === '*' && ! $existingType instanceof ArrayType) {
                $base->addProperty($key, (new ArrayType)->addProperties($existingType));
            }
            if ($next !== '*' && $existingType instanceof ArrayType) {
                $base->addProperty($key, (new ObjectType)->addProperties($existingType));
            }

            return $this->getOrCreateDeepTypeContainer(
                $base->properties[$key],
                collect($path)->splice(1)->values()->all(),
            );
        }
    }

    private function extractTypeFromParameter($parameter)
    {
        $paramType = $parameter->schema->type;

        $paramType->setDescription($parameter->description);
        $paramType->example($parameter->example);

        return $paramType;
    }
}
