<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocParser;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

class PhpDoc
{
    public static function getTokenizerAndParser()
    {
        if (class_exists(\PHPStan\PhpDocParser\ParserConfig::class)) {
            $config = new \PHPStan\PhpDocParser\ParserConfig(usedAttributes: ['lines' => true, 'indexes' => true]);
            $lexer = new Lexer($config);
            $constExprParser = new ConstExprParser($config);
            $typeParser = new TypeParser($config, $constExprParser);
            $phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);

            return [$lexer, $phpDocParser, $typeParser];
        }

        $lexer = new Lexer;
        $constExprParser = new ConstExprParser;
        $typeParser = new TypeParser($constExprParser);

        return [$lexer, new PhpDocParser($typeParser, $constExprParser), $typeParser];
    }

    public static function parse(string $docComment, ?FileNameResolver $nameResolver = null): PhpDocNode
    {
        $docComment = Str::replace(['@body'], '@var', $docComment);

        [$lexer, $phpDocParser] = static::getTokenizerAndParser();

        $tokens = new TokenIterator($lexer->tokenize($docComment));

        /** @var PhpDocNode $node */
        $node = $phpDocParser->parse($tokens);

        static::addSummaryAttributes($node);

        if ($nameResolver) {
            $tagValues = [
                ...$node->getParamTagValues(),
                ...$node->getMixinTagValues(),
                ...$node->getExtendsTagValues(),
                ...$node->getUsesTagValues(),
                ...$node->getReturnTagValues(),
                ...$node->getReturnTagValues('@response'),
                ...$node->getReturnTagValues('@scramble-return'),
                ...$node->getVarTagValues(),
                ...$node->getThrowsTagValues(),
            ];

            foreach ($tagValues as $tagValue) {
                PhpDocTypeWalker::traverse($tagValue->type, [
                    new ResolveFqnPhpDocTypeVisitor($nameResolver),
                ]);
            }
        }

        return $node;
    }

    public static function addSummaryAttributes(PhpDocNode $phpDoc)
    {
        $text = collect($phpDoc->children)
            ->filter(fn ($v) => $v instanceof PhpDocTextNode)
            ->map(fn (PhpDocTextNode $n) => $n->text)
            ->implode("\n");

        $text = Str::of($text)
            ->trim()
            ->explode("\n\n", 2);

        $phpDoc->setAttribute('summary', trim($text[0] ?? ''));
        $phpDoc->setAttribute('description', trim($text[1] ?? ''));
    }
}
