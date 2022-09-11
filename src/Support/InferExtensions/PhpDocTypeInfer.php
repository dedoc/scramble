<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\PhpDoc;
use PhpParser\Node;

class PhpDocTypeInfer
{
    public function getExpressionType(Node $node, Scope $scope)
    {
        if ($node instanceof Node\Expr\ArrayItem && $doc = $node->getDocComment()) {
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
    }
}
