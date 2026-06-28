<?php

namespace Dedoc\Scramble\Reflection;

use Dedoc\Scramble\Support\Type\ObjectType;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class JsonApiRelationship
{
    public function __construct(
        public readonly string $name,
        public readonly ObjectType $resourceType,
        public readonly bool $isCollection,
        public readonly ?PhpDocNode $phpDoc = null,
    ) {}
}
