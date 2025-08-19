<?php

use Dedoc\Scramble\Infer\DefinitionBuilders\ClassAstSignatureDefinitionBuilder;
use Dedoc\Scramble\Infer\Services\FileParser;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

include 'vendor/autoload.php';

$source = file_get_contents($path = __DIR__.'/../core.php');

app()->singleton(FileParser::class, function () {
    return new FileParser(
        (new ParserFactory)->createForHostVersion()
    );
});

$ast = FileParser::getInstance()->parseContent($source);
foreach ($ast->getStatements() as $namespaceNode) {
    if (! $namespaceNode instanceof Namespace_) {
        throw new Exception("Stub file [$path] must contain namespace");
    }

    foreach ($namespaceNode->stmts as $declaration) {
        if (! $declaration instanceof Class_) {
            throw new Exception("Stub file [$path] must contain only classes, ".$declaration::class.' found');
        }

        $def = (new ClassAstSignatureDefinitionBuilder(
            $path,
            $namespaceNode->name->name,
            $declaration,
        ))->build();

        dd($def);
    }

}

// $classes = (new NodeFinder())
//    ->findInstanceOf(
//        $ast->getStatements(),
//
//    )

dd($ast);

dd(print_tokens(token_get_all($source)));

function token_const_name(int $id): ?string
{
    static $map = null;

    if ($map === null) {
        // Get all defined constants grouped by category
        $consts = get_defined_constants(true);

        // PHP tokenizer constants are under "tokenizer"
        // but your custom list might be in "user"
        $map = [];
        foreach (['tokenizer', 'user'] as $group) {
            if (isset($consts[$group])) {
                foreach ($consts[$group] as $name => $value) {
                    if (str_starts_with($name, 'T_')) {
                        $map[$value] = $name;
                    }
                }
            }
        }
    }

    return $map[$id] ?? null;
}
function print_tokens(array $tokens): array
{
    return array_map(function ($token) {
        if (! is_array($token)) {
            return $token;
        }

        return [
            token_const_name($token[0]),
            $token[1],
            $token[2],
        ];
    }, $tokens);
}
