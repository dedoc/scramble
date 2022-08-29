<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use PhpParser\Node;

class ReturnTypeGettingExtensions
{
    public static $extensions = [];

    public function shouldHandle($node)
    {
        return true;
    }

    public function leave(Node $node)
    {
        $type = array_reduce(
            static::$extensions,
            function ($acc, $extensionClass) use ($node) {
                $type = (new $extensionClass)->getNodeReturnType($node, $node->getAttribute('scope'));
                if ($type) {
                    return $type;
                }

                return $acc;
            },
        );

        if ($type) {
            $node->setAttribute('type', $type);
        }
    }
}
