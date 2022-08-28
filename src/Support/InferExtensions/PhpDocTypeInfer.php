<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\TypeHandlers\PhpDocTypeWalker;
use Dedoc\Scramble\Support\TypeHandlers\ResolveFqnPhpDocTypeVisitor;
use PhpParser\Node;

class PhpDocTypeInfer
{
    public function getNodeReturnType(Node $node, Scope $scope)
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

            $node->getAttribute('type')->setAttribute('docNode', $docNode);
        }
    }
}
