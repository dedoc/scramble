<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;

/**
 * Handler that adds info from PHPDoc to the node. Thanks to this, other extensions
 * can get more information for the returning types.
 */
class PhpDocHandler
{
    public function shouldHandle(Node $node)
    {
        return (bool) $node->getDocComment() || count($node->getComments());
    }

    public function leave(Node $node, Scope $scope): ?Type
    {
        if (! $parsedPhpDoc = $node->getAttribute('parsedPhpDoc')) {
            return null;
        }
        /** @var PhpDocNode $parsedPhpDoc */
        $parsedPhpDoc->setAttribute('sourceClass', $scope->classDefinition()?->name);
        $scope->getType($node)->setAttribute('docNode', $parsedPhpDoc);

        if ($node instanceof Node\Stmt\Return_ && $node->expr) {
            $scope->getType($node->expr)->setAttribute('docNode', $parsedPhpDoc);
        }

        if ($node instanceof Node\Stmt\ClassMethod && ($methodType = $scope->functionDefinition()?->type)) {
            $thrownExceptions = collect($parsedPhpDoc->getThrowsTagValues())
                ->flatMap(function (ThrowsTagValueNode $t) {
                    $type = PhpDocTypeHelper::toType($t->type);

                    if ($type instanceof Union) {
                        return $type->types;
                    }

                    return [$type];
                });

            $methodType->exceptions = [
                ...$methodType->exceptions,
                ...$thrownExceptions,
            ];
        }

        return null;
    }
}
