<?php

namespace Dedoc\Scramble;

class OpenAPITraverser
{
    public function __construct(
        private array $visitors = [],
    ) {
    }

    public function traverse($type): void
    {
        if (is_scalar($type) || $type === null) {
            return;
        }

        $this->enterType($type);

        $propertiesWithNodes = $this->getNodes($type);

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

    private function enterType($type): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->enter($type);
        }
    }

    private function leaveType($type): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->leave($type);
        }
    }

    private function getNodes($instance)
    {
        return array_keys(get_object_vars($instance));
    }
}
