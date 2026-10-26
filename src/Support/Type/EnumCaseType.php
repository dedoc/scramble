<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

class EnumCaseType extends ObjectType
{
    public function __construct(
        string $name,
        public string $caseName,
    ) {
        parent::__construct($name);
    }

    public function getPropertyType(string $propertyName, Scope $scope = new GlobalScope): Type
    {
        if ($propertyName === 'name') {
            return new LiteralStringType($this->caseName);
        }

        if ($propertyName === 'value' && ($value = $scope->index->getClass($this->name)?->enumCases[$this->caseName] ?? null)) {
            return $value;
        }

        return parent::getPropertyType($propertyName, $scope);
    }

    public function isSame(Type $type)
    {
        return $type instanceof static && $type->toString() === $this->toString();
    }

    public function toString(): string
    {
        return "{$this->name}::{$this->caseName}";
    }
}
