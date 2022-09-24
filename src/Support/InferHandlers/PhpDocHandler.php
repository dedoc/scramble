<?php

namespace Dedoc\Scramble\Support\InferHandlers;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Handler that adds info from PHPDoc to the node. Thanks to this, other extensions
 * can get more information for the returning types.
 */
class PhpDocHandler
{
    public function shouldHandle(Node $node)
    {
        return (bool) $node->getDocComment();
    }

    public function leave(Node $node, Scope $scope): ?Type
    {
        if ($node instanceof Expr\ArrayItem && $doc = $node->getDocComment()) {
            $docNode = PhpDoc::parse($doc->getText());

            if (count($varTagValues = $docNode->getVarTagValues())) {
                foreach ($varTagValues as $varTagValue) {
                    if (! $varTagValue->type) {
                        continue;
                    }
                    PhpDocTypeWalker::traverse($varTagValue->type, [new ResolveFqnPhpDocTypeVisitor($scope->namesResolver)]);
                }
            }

            $scope->getType($node)->setAttribute('docNode', $docNode);
        }

        return null;
    }
}
