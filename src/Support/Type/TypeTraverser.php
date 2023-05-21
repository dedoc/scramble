<?php

namespace Dedoc\Scramble\Support\Type;

class TypeTraverser
{
    public function __construct(
        private array $visitors = [],
    ) {
    }

    public function traverse(Type $type): void
    {
        $this->enterType($type);

        $propertiesWithNodes = $type->nodes();

        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $type->$propertyWithNode;
            if (! is_array($node)) {
                $this->traverse($node);
            } else {
                foreach ($node as $item) {
                    $this->traverse($item);
                }
            }
        }

        $this->leaveType($type);
    }

    private function enterType(Type $type): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->enter($type);
        }
    }

    private function leaveType(Type $type): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->leave($type);
        }
    }
}
