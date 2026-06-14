<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Support\Type\Type;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class ClassPropertyDefinition
{
    private ?PhpDocNode $docNode = null;

    public function __construct(
        public Type $type,
        public ?Type $defaultType = null,
        public readonly bool $isStatic = false,
        public readonly PropertyVisibility $visibility = PropertyVisibility::Public,
        /** @var AttributeDefinition[] */
        public readonly array $attributes = [],
        private readonly ?PendingDocComment $pendingDocComment = null,
    ) {}

    public function getDocNode(): ?PhpDocNode
    {
        if (! $this->pendingDocComment) {
            return null;
        }

        return $this->docNode ??= $this->pendingDocComment->resolve();
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if ($name === null) {
            return $this->attributes;
        }

        return array_values(array_filter(
            $this->attributes,
            function (AttributeDefinition $attribute) use ($name, $flags) {
                if ($flags & AttributeDefinition::IS_INSTANCEOF) {
                    return is_a($attribute->name, $name, true);
                }

                return $attribute->name === $name;
            },
        ));
    }
}
