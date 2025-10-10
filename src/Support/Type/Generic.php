<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;
use Traversable;

class Generic extends ObjectType
{
    /**
     * The list of concrete types for this generic.
     *
     * @var array<int, Type>
     */
    public array $templateTypes = [];

    public function __construct(
        string $name,
        array $templateTypes = []
    ) {
        parent::__construct($name);
        if (! array_is_list($templateTypes)) {
            throw new \InvalidArgumentException('[$templateTypes] for Generic must be a list.');
        }
        $this->templateTypes = $templateTypes;
    }

    public function nodes(): array
    {
        return ['templateTypes'];
    }

    public function accepts(Type $otherType): bool
    {
        if ($this->name === 'iterable') {
            return $otherType instanceof ArrayType
                || $otherType instanceof KeyedArrayType
                || $otherType->isInstanceOf(Traversable::class);
        }

        return parent::accepts($otherType);
    }

    public function getPropertyType(string $propertyName, Scope $scope = new GlobalScope): Type
    {
        $propertyType = parent::getPropertyType($propertyName, $scope);

        $templateNameToIndexMap = ($classDefinition = $scope->index->getClass($this->name))
            ? array_flip(array_map(fn ($t) => $t->name, $classDefinition->templateTypes))
            : [];

        /** @var array<string, Type> $inferredTemplates */
        $inferredTemplates = collect($templateNameToIndexMap)
            ->mapWithKeys(fn ($i, $name) => [$name => $this->templateTypes[$i] ?? new UnknownType])
            ->toArray();

        return (new TypeWalker)->replace($propertyType, function (Type $t) use ($inferredTemplates) {
            if (! $t instanceof TemplateType) {
                return null;
            }

            if (array_key_exists($t->name, $inferredTemplates)) {
                return $inferredTemplates[$t->name];
            }

            return null;
        });
    }

    public function toString(): string
    {
        return sprintf(
            '%s<%s>',
            $this->name,
            implode(', ', array_map(fn ($t) => $t->toString(), $this->templateTypes))
        );
    }
}
