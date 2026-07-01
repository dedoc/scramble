<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class CodeLocation
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
    )
    {
    }

    /**
     * Array items combine PHPDoc both from array item node, and value node.
     */
    public static function fromArrayItemType(ArrayItemType_ $type): self
    {
        /** @var PhpDocNode|null $arrayItemDocNode */
        $arrayItemDocNode = $type->getAttribute('phpDoc');
        /** @var PhpDocNode|null $valueDocNode */
        $valueDocNode = $type->getAttribute('phpDoc');

        $context = $arrayItemDocNode?->getAttribute('sourceClass')
            ?? $valueDocNode?->getAttribute('sourceClass');

        dd($context);

    }
}
