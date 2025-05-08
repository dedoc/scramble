<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\InferExtensions\PaginateMethodsReturnTypeExtension;
use PhpParser\Node;

class PaginatorsCandidatesBuilder implements IndexBuilder
{
    public function __construct(public readonly Bag $bag) {}

    public function afterAnalyzedNode(Scope $scope, Node $node): void
    {
        if (! $node instanceof Node\Expr\StaticCall && ! $node instanceof Node\Expr\MethodCall) {
            return;
        }

        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        if (! in_array($node->name->name, PaginateMethodsReturnTypeExtension::PAGINATE_METHODS)) {
            return;
        }

        $this->bag->set(
            $key = 'paginatorCandidates',
            [
                ...($this->bag->data[$key] ?? []),
                $node,
            ],
        );
        $this->bag->set('scope', $scope);
    }
}
