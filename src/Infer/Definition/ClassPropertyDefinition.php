<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\PhpDoc;
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
        public ?string $docComment = null,
        public ?string $declaringFileName = null,
    ) {}

    public function getDocNode(): ?PhpDocNode
    {
        if (! $this->docComment) {
            return null;
        }

        return $this->docNode ??= PhpDoc::parse(
            $this->docComment,
            $this->declaringFileName ? FileNameResolver::createForFile($this->declaringFileName) : null,
        );
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
