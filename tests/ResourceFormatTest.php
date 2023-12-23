<?php

use Dedoc\Scramble\PhpDoc\PhpDocParser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

function getFormatFromDoc(string $phpDoc)
{
    $lexer = new Lexer();
    $tokenized = $lexer->tokenize($phpDoc);

    $constExprParser = new ConstExprParser();
    $typeParser = new TypeParser($constExprParser);
    $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

    $tokens = new TokenIterator($tokenized);

    $node = $phpDocParser->parse($tokens);
    $withFormat = $node->getTagsByName("@format");
    if(count($withFormat) === 0) return null;

    $withFormat = reset($withFormat);

    return $withFormat->value->value;
}

it('handles string type with format', function ($phpDoc, $expectedFormat) {
    $result = getFormatFromDoc($phpDoc);

    expect($result)->toBe($expectedFormat);
})->with([
    ["/** @var string \n * @format email */", "email"],
    ["/** @var string \n * @format date-time */", "date-time"],
    ["/** @var string \n * @format date */", "date"],
    ["/** @var string \n * @format time */", "time"],
    ["/** @var string \n * @format ipv4 */", "ipv4"],
    ["/** @var string \n * @format ipv6 */", "ipv6"],
    ["/** @var string \n * @format uri */", "uri"],
    ["/** @var string \n * @format hostname */", "hostname"],
    ["/** @var string \n * @format uuid */", "uuid"],
    ["/** @var string \n * @format uri-reference */", "uri-reference"],
    ["/** @var string \n * @format uri-template */", "uri-template"],
    ["/** @var string \n * @format iri */", "iri"],
    ["/** @var string \n * @format iri-reference */", "iri-reference"],
    ["/** @var string \n * @format idn-email */", "idn-email"],
    ["/** @var string \n * @format idn-hostname */", "idn-hostname"],
    ["/** @var string \n * @format password */", "password"],
]);
