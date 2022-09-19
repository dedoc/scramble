<?php

use Dedoc\Scramble\Infer\TypeInferringVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

it('infers response factory expressions', function (string $expression, string $expectedType) {
    $type = getExpressionType($expression);
response();
    expect($type->toString())->toBe($expectedType);
})->with([
    ['response()', 'Illuminate\Contracts\Routing\ResponseFactory'],
    ['response("hey", 401)', 'Illuminate\Http\Response<string(hey), int(401), array{}>'],
    ['response()->noContent()', 'Illuminate\Http\Response<string(), int(204), array{}>'],
    ['response()->json()', 'Illuminate\Http\JsonResponse<array{}, int(200), array{}>'],
    ['response()->json(status: 329)', 'Illuminate\Http\JsonResponse<array{}, int(329), array{}>'],
    ["response()->make('Hello')", 'Illuminate\Http\Response<string(Hello), int(200), array{}>'],
]);

function getExpressionType(string $expression)
{
    $code = "<?php $expression;";

    $fileAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse($code);

    $infer = app()->make(TypeInferringVisitor::class, ['namesResolver' => fn ($s) => $s]);
    $traverser = new NodeTraverser;
    $traverser->addVisitor($infer);
    $traverser->traverse($fileAst);

    return $infer->scope->getType($fileAst[0]->expr);
}
