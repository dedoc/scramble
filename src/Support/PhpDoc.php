<?php

namespace Dedoc\Documentor\Support;

use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

class PhpDoc
{
    public static function parse(string $docComment): PhpDocNode
    {
        $docComment = Str::replace(['@response'], '@return', $docComment);

        $lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $typeParser = new TypeParser($constExprParser);
        $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

        $tokens = new TokenIterator($lexer->tokenize($docComment));

        return $phpDocParser->parse($tokens);
    }
}
