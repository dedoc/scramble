<?php

namespace Dedoc\Scramble\Support\Type;

class TypeTraverser
{
    /**
     * @param  TypeVisitor[]  $visitors
     */
    public function __construct(
        private array $visitors = [],
    ) {}

    public function traverse(Type $type): Type
    {
        $enterResult = $this->enterType($type);

        if ($enterResult === TypeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN) {
            return $type;
        }

        if ($enterResult instanceof Type) {
            $type = $enterResult;
        }

        if ($enterResult !== TypeVisitor::DONT_TRAVERSE_CHILDREN) {
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
        }

        $leaveResult = $this->leaveType($type);
        if ($leaveResult instanceof Type) {
            $type = $leaveResult;
        }

        return $type;
    }

    private function enterType(Type $type): Type|int|null
    {
        $result = null;
        foreach ($this->visitors as $visitor) {
            $result = $visitor->enter($result instanceof Type ? $result : $type);
        }

        return $result;
    }

    private function leaveType(Type $type): Type|int|null
    {
        $result = null;
        foreach ($this->visitors as $visitor) {
            $result = $visitor->leave($result instanceof Type ? $result : $type);
        }

        return $result;
    }
}
