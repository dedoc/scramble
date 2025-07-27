<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * @internal
 */
class TypeBasedRulesDocumentationRetriever implements RulesDocumentationRetriever
{
    public function __construct(
        public readonly Scope $scope,
        public readonly Type $type,
    ) {}

    /**
     * @return array<string, PhpDocNode>
     */
    public function getDocNodes(): array
    {
        /** @var ArrayItemType_[] $arrayItemsNodes */
        $arrayItemsNodes = (new TypeWalker)->findAll(
            ReferenceTypeResolver::getInstance()->resolve($this->scope, $this->type),
            fn (Type $t) => $t instanceof ArrayItemType_ && $t->getAttribute('docNode'),
        );

        return collect($arrayItemsNodes)
            ->mapWithKeys(function (ArrayItemType_ $item) {
                if (! $key = $this->getArrayItemKey($item)) {
                    return [];
                }

                $parsedDoc = $item->getAttribute('docNode');
                if (! $parsedDoc instanceof PhpDocNode) {
                    return [];
                }

                return [$key => $parsedDoc];
            })
            ->all();
    }

    private function getArrayItemKey(ArrayItemType_ $item): ?string
    {
        if (is_string($item->key)) {
            return $item->key;
        }

        if ($item->keyType) {
            $resolvedKeyType = ReferenceTypeResolver::getInstance()->resolve($this->scope, $item->keyType);

            return $resolvedKeyType instanceof LiteralStringType
                ? $resolvedKeyType->value
                : null;
        }

        return null;
    }
}
