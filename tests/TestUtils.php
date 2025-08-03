<?php

namespace Dedoc\Scramble\Tests;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Type;
use PHPStan\PhpDocParser\Parser\TokenIterator;

class TestUtils
{
    public static function parseType(string $type): Type
    {
        [$lexer, $_, $typeParser] = PhpDoc::getTokenizerAndParser();

        $tokens = new TokenIterator($lexer->tokenize($type));

        $phpDocType = $typeParser->parse($tokens);

        return PhpDocTypeHelper::toType($phpDocType);
    }
}
