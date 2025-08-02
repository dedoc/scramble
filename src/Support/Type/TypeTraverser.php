<?php

namespace Dedoc\Scramble\Support\Type;

class TypeTraverser
{
    public function __construct(
        private array $visitors = [],
    ) {}

    public function traverse(Type $type): Type
    {
        $enterResult = $this->enterType($type);
        if ($enterResult instanceof Type) {
            $type = $enterResult;
        }

        $propertiesWithNodes = $type->nodes();

        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $type->$propertyWithNode;
            if (! is_array($node)) {
                $type->$propertyWithNode = $this->traverse($node);
            } else {
                foreach ($node as $index => $item) {
                    $type->$propertyWithNode[$index] = $this->traverse($item);
                }
            }
        }

        $leaveResult = $this->leaveType($type);
        if ($leaveResult instanceof Type) {
            $type = $leaveResult;
        }

        return $type;
    }

    private function enterType(Type $type): mixed
    {
        $result = null;
        foreach ($this->visitors as $visitor) {
            $result = $visitor->enter($result ?: $type);
        }
        return $result;
    }

    private function leaveType(Type $type): mixed
    {
        $result = null;
        foreach ($this->visitors as $visitor) {
            $result = $visitor->leave($result ?: $type);
        }
        return $result;
    }
}
