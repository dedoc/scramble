<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node;

/**
 * @internal
 *
 * @phpstan-type NamePosition array{?string, int}
 */
class MethodQuery
{
    /**
     * @var array{NamePosition, Type}[]
     */
    private array $argumentsOverrides = [];

    private array $types = [];

    private ?Scope $scope = null;

    public function __construct(private Infer $infer) {}

    public static function make(Infer $infer): static
    {
        return new static($infer);
    }

    /**
     * @param  NamePosition  $positionName
     */
    public function withArgumentType(array $positionName, Type $type): static
    {
        $this->argumentsOverrides[] = [$positionName, $type];

        return $this;
    }

    public function from(Infer\Definition\ClassDefinition $classDefinition, string $methodName): static
    {
        /** @var Bag<array{scope: Scope, types: Type[], _hasReplaced: bool}> $bag */
        $bag = new Bag;

        if (! $methodDefinition = $classDefinition->getMethodDefinition($methodName)) {
            return $this;
        }

        (new MethodAnalyzer($this->infer->index, $classDefinition))
            ->analyze($methodDefinition, [
                new class($bag, $this->argumentsOverrides) implements IndexBuilder
                {
                    /**
                     * @param  Bag<array{scope: Scope, types: Type[], _hasReplaced: bool}>  $bag
                     * @param  array{array{?string, int}, Type}[]  $argumentsOverrides
                     */
                    public function __construct(private Bag $bag, private array $argumentsOverrides = []) {}

                    public function afterAnalyzedNode(Scope $scope, Node $node): void
                    {
                        $this->replaceArguments($scope, $node);

                        $types = $this->bag->data['types'] ?? [];
                        $this->bag->set('scope', $scope);

                        if ($type = $scope->getType($node)) {
                            $types[] = $type;
                        }

                        $this->bag->set('types', $types);
                    }

                    private function replaceArguments(Scope $scope, Node $node): void
                    {
                        if ($this->bag->data['_hasReplaced'] ?? false) {
                            return;
                        }

                        foreach ($this->argumentsOverrides as $argumentsOverride) {
                            [[$name, $position], $type] = $argumentsOverride;

                            $targetName = $name ?: array_keys($scope->variables)[$position];

                            $scope->variables[$targetName][0]['type'] = $type;
                        }

                        $this->bag->set('_hasReplaced', true);
                    }
                },
            ]);

        $this->scope = $bag->data['scope'] ?? null;
        $this->types = $bag->data['types'] ?? [];

        return $this;
    }

    public function getTypes(callable $typesFinder)
    {
        return collect($this->types)->filter($typesFinder)->values();
    }

    public function getScope(): ?Scope
    {
        return $this->scope;
    }
}
