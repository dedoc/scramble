<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\UnresolvableArgumentTypeBag;

class ObjectType extends AbstractType
{
    /**
     * @var array<string, Type>
     */
    public array $propertyTypes = [];

    public function __construct(
        public string $name,
    ) {}

    public function nodes(): array
    {
        return ['propertyTypes'];
    }

    public function isInstanceOf(string $className): bool
    {
        if ($this->name === 'iterable' && $className === 'iterable') {
            return true;
        }

        return is_a($this->name, $className, true);
    }

    public function isSame(Type $type)
    {
        if (! $type instanceof static || $type->name !== $this->name) {
            return false;
        }

        if (count($type->propertyTypes) !== count($this->propertyTypes)) {
            return false;
        }

        foreach ($this->propertyTypes as $propertyName => $propertyType) {
            if (! isset($type->propertyTypes[$propertyName]) || ! $propertyType->isSame($type->propertyTypes[$propertyName])) {
                return false;
            }
        }

        return true;
    }

    public function withAssignedPropertyType(string $propertyName, Type $assignedType): static
    {
        $result = $this->clone();
        $result->propertyTypes[$propertyName] = $assignedType;

        return $result;
    }

    public function getPropertyType(string $propertyName, Scope $scope = new GlobalScope): Type
    {
        if ($propertyType = app(ExtensionsBroker::class)->getPropertyType(new PropertyFetchEvent(
            instance: $this,
            name: $propertyName,
            scope: $scope,
        ))) {
            return $propertyType;
        }

        if ($propertyType = $this->propertyTypes[$propertyName] ?? null) {
            return $propertyType;
        }

        $definition = $scope->index->getClass($this->name);

        if (! $propertyDefinition = $definition?->getPropertyDefinition($propertyName)) {
            return new UnknownType("Cannot get a property type [$propertyName] on type [{$this->name}]");
        }

        return $propertyDefinition->type ?: $propertyDefinition->defaultType;
    }

    public function getMethodDefinition(string $methodName, Scope $scope = new GlobalScope): ?FunctionLikeDefinition
    {
        $classDefinition = $scope->index->getClass($this->name);

        return $classDefinition?->getMethodDefinition($methodName, $scope);
    }

    public function getMethodReturnType(string $methodName, array|ArgumentTypeBag $arguments = [], Scope $scope = new GlobalScope): Type
    {
        $arguments = $arguments instanceof ArgumentTypeBag ? $arguments : new UnresolvableArgumentTypeBag($arguments);
        $classDefinition = $scope->index->getClass($this->name);

        if ($returnType = app(ExtensionsBroker::class)->getMethodReturnType(new MethodCallEvent(
            instance: $this,
            name: $methodName,
            scope: $scope,
            arguments: $arguments,
            methodDefiningClassName: $definingClassName = $classDefinition ? $classDefinition->getMethodDefiningClassName($methodName, $scope->index) : $this->name,
        ))) {
            return $returnType;
        }

        /*
         * For now, when parent class is in `vendor`, we may do not know that certain definition exists.
         */
        if (! $methodDefinition = $this->getMethodDefinition($methodName)) {
            return new UnknownType("No method {$definingClassName}@{$methodName} definition found, it may be located in `vendor` which is not analyzed.");
        }

        $returnType = $methodDefinition->getReturnType();

        // Here templates should be replaced for generics and arguments should be taken into account.
        return $returnType instanceof TemplateType && $returnType->is
            ? $returnType->is
            : $returnType;
    }

    public function accepts(Type $otherType): bool
    {
        if (! $otherType instanceof ObjectType) {
            return false;
        }

        return is_a($otherType->name, $this->name, true);
    }

    public function toString(): string
    {
        return $this->name;
    }
}
