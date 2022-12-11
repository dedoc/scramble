<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class PendingReturnType extends AbstractType
{
    public Node $node;

    /**
     * When pending return cannot be resolved in the end, this type will be used.
     */
    public Type $defaultType;

    public Scope $scope;

    public function __construct(Node $node, Type $defaultType, Scope $scope)
    {
        $this->node = $node;
        $this->defaultType = $defaultType;
        $this->scope = $scope;
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'pending-type';
    }

    public function getDefaultType()
    {
        foreach ($this->attributes() as $name => $value) {
            $this->defaultType->setAttribute($name, $value);
        }

        return $this->defaultType;
    }
}
