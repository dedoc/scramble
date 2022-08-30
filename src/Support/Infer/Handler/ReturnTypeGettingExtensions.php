<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use PhpParser\Node;

class ReturnTypeGettingExtensions
{
    public static $extensions = [];

    public function shouldHandle()
    {
        return true;
    }

    public function leave(Node $node, Scope $scope)
    {
        $type = array_reduce(
            static::$extensions,
            function ($acc, $extensionClass) use ($node, $scope) {
                $type = (new $extensionClass)->getNodeReturnType($node, $scope);
                if ($type) {
                    return $type;
                }

                return $acc;
            },
        );

        if ($type) {
            $scope->setType($node, $type);
        }
    }
}
