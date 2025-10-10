<?php

namespace Dedoc\Scramble\PhpDoc;

use PHPStan\PhpDocParser\Ast;
use PHPStan\PhpDocParser\Parser\PhpDocParser as VendorPhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;

class PhpDocParser extends VendorPhpDocParser
{
    public function parseTagValue(TokenIterator $tokens, string $tag): Ast\PhpDoc\PhpDocTagValueNode
    {
        if ($tag === '@response') {
            $tag = '@return';
        }

        if ($tag === '@scramble-return') {
            $tag = '@return';
        }

        return parent::parseTagValue($tokens, $tag);
    }
}
