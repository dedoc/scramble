<?php

namespace Dedoc\Scramble\Support\InferHandlers;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
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
        return (bool) $node->getDocComment() || count($node->getComments());
    }

    public function leave(Node $node, Scope $scope): ?Type
    {
        if ($node instanceof Expr\ArrayItem && $doc = $node->getDocComment()) {
            $docNode = $this->getDocNode($scope, $doc);

            $scope->getType($node)->setAttribute('docNode', $docNode);
        }

        if ($node instanceof Node\Stmt\Return_ && count($node->getComments()) && ! $node->getDocComment()) {
            $docText = collect($node->getComments())
                ->map(fn (Comment $c) => $c->getReformattedText())
                ->join("\n");

            $docText = (string) Str::of($docText)->replace(['//', ' * ', '/*', '*/'], '')->trim();

            $docNode = $this->getDocNode($scope, new Doc("/** $docText */"));

            if ($node->expr) {
                $scope->getType($node->expr)->setAttribute('docNode', $docNode);
            }
        }

        if ($node instanceof Node\Stmt\Return_ && $doc = $node->getDocComment()) {
            $docNode = $this->getDocNode($scope, $doc);

            if ($node->expr) {
                $scope->getType($node->expr)->setAttribute('docNode', $docNode);
            }
        }

        return null;
    }

    private function getDocNode(Scope $scope, Doc $doc)
    {
        $docNode = PhpDoc::parse($doc->getText());

        if (count($varTagValues = $docNode->getVarTagValues())) {
            foreach ($varTagValues as $varTagValue) {
                if (! $varTagValue->type) {
                    continue;
                }
                PhpDocTypeWalker::traverse($varTagValue->type, [new ResolveFqnPhpDocTypeVisitor($scope->namesResolver)]);
            }
        }

        return $docNode;
    }
}
