<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;

class PhpDocTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
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
