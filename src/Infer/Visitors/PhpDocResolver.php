<?php

namespace Dedoc\Scramble\Infer\Visitors;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Str;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

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

        if ($doc) {
            $node->setAttribute('parsedPhpDoc', $this->parseDocs($doc));
        }

        return null;
    }

    private function parseDocs(Doc $doc)
    {
        $docNode = PhpDoc::parse($doc->getText());

        $tagValues = [
            ...$docNode->getReturnTagValues(),
            ...$docNode->getReturnTagValues('@response'),
            ...$docNode->getVarTagValues(),
            ...$docNode->getThrowsTagValues(),
        ];

        foreach ($tagValues as $tagValue) {
            if (! $tagValue->type) {
                continue;
            }
            PhpDocTypeWalker::traverse($tagValue->type, [
                new ResolveFqnPhpDocTypeVisitor($this->nameResolver),
            ]);
        }

        return $docNode;
    }
}
