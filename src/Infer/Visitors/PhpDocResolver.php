<?php

namespace Dedoc\Scramble\Infer\Visitors;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Str;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class PhpDocResolver extends NodeVisitorAbstract
{
    private FileNameResolver $nameResolver;

    public function __construct(FileNameResolver $nameResolver)
    {
        $this->nameResolver = $nameResolver;
    }

    public function enterNode(Node $node)
    {
        if (! $node->getDocComment() && empty($node->getComments())) {
            return null;
        }

        $doc = $node->getDocComment();

        if (
            ($node instanceof Expr\ArrayItem
            || $node instanceof Node\Stmt\Return_)
            && ! $doc
        ) {
            $docText = collect($node->getComments())
                ->map(fn (Comment $c) => $c->getReformattedText())
                ->join("\n");

            $docText = (string) Str::of($docText)->replace(['//', ' * ', '/*', '*/'], '')->trim();

            $doc = new Doc("/** $docText */");
        }

        if (! $doc) {
            return null;
        }

        $parsedDoc = $this->parseDocs($doc);

        $node->setAttribute('parsedPhpDoc', $parsedDoc);

        /*
         * Parsed doc is propagated to the child expressions nodes, so it is easier for the consumer
         * to get to the php doc when needed. For example, when some method call is annotated with phpdoc,
         * we'd want to get this doc from the method call node, not an expression one.
         */
        if ($node instanceof Node\Stmt\Expression) {
            $node->expr->setAttribute('parsedPhpDoc', $parsedDoc);
        }

        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Expr\Assign) {
            $node->expr->expr->setAttribute('parsedPhpDoc', $parsedDoc);
        }
    }

    private function parseDocs(Doc $doc): PhpDocNode
    {
        return PhpDoc::parse($doc->getText(), $this->nameResolver);
    }
}
