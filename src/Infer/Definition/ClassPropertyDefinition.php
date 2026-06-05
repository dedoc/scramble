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

    public function hasAttribute(string $name): bool
    {
        foreach ($this->attributes as $attribute) {
            if (is_a($attribute->name, $name, true)) {
                return true;
            }
        }

        return false;
    }
}
