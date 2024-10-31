<?php

namespace Dedoc\Scramble;

use Illuminate\Support\Str;

class OpenApiTraverser
{
    public function __construct(
        private array $visitors = [],
    ) {}

    public function traverse($type, $path = []): void
    {
        if (is_scalar($type) || $type === null) {
            return;
        }

        $this->enterType($type, $path);

        $propertiesWithNodes = $this->getNodes($type);

        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $type->$propertyWithNode;

            if (! is_array($node)) {
                $this->traverse($node, [...$path, $propertyWithNode]);
            } else {
                foreach ($node as $i => $item) {
                    $this->traverse($item, [...$path, $propertyWithNode, $i]);
                }
            }
        }

        $this->leaveType($type, $path);
    }

    private function enterType($type, $path): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->enter($type, $path);
        }
    }

    private function leaveType($type, $path): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->leave($type, $path);
        }
    }

    private function getNodes($instance)
    {
        if (! is_object($instance)) {
            return [];
        }

        return array_keys(get_object_vars($instance));
    }

    public static function normalizeJsonPointerReferenceToken(string $referenceToken)
    {
        return Str::replace(['~', '/'], ['~0', '~1'], addslashes($referenceToken));
    }
}
