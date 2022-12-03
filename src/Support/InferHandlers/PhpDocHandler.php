<?php

namespace Dedoc\Scramble\Support\InferHandlers;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Str;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
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
        if ($node instanceof Expr\ArrayItem && count($node->getComments()) && ! $node->getDocComment()) {
            $docText = collect($node->getComments())
                ->map(fn (Comment $c) => $c->getReformattedText())
                ->join("\n");

            $docText = (string) Str::of($docText)->replace(['//', ' * ', '/*', '*/'], '')->trim();

            $docNode = $this->getDocNode($scope, new Doc("/** $docText */"));

            $scope->getType($node)->setAttribute('docNode', $docNode);
        }

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

        if ($node instanceof Node\Stmt\ClassMethod && ($methodType = $scope->getType($node)) && $doc = $node->getDocComment()) {
            $docNode = $this->getDocNode($scope, $doc);

            $thrownExceptions = collect($docNode->getThrowsTagValues())
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

    private function getDocNode(Scope $scope, Doc $doc)
    {
        $docNode = PhpDoc::parse($doc->getText());

        $tagValues = [
            ...$docNode->getVarTagValues(),
            ...$docNode->getThrowsTagValues(),
        ];

        foreach ($tagValues as $tagValue) {
            if (! $tagValue->type) {
                continue;
            }
            PhpDocTypeWalker::traverse($tagValue->type, [new ResolveFqnPhpDocTypeVisitor($scope->namesResolver)]);
        }

        return $docNode;
    }
}
