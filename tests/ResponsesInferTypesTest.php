<?php

it('infers response factory expressions', function (string $expression, string $expectedType, ?array $expectedAttributes = null) {
    $type = getExpressionType($expression);

    expect($type->toString())->toBe($expectedType);

    if ($expectedAttributes !== null) {
        expect($type->attributes())->toBe($expectedAttributes);
    }
})->with([
    ['response()', 'Illuminate\Contracts\Routing\ResponseFactory'],
    ['response("hey", 401)', 'Illuminate\Http\Response<string(hey), int(401), array<mixed>>'],
    ['response()->noContent()', 'Illuminate\Http\Response<string(), int(204), array<mixed>>'],
    ['response()->json()', 'Illuminate\Http\JsonResponse<array<mixed>, int(200), array<mixed>>'],
    ['response()->json(status: 329)', 'Illuminate\Http\JsonResponse<array<mixed>, int(329), array<mixed>>'],
    ["response()->make('Hello')", 'Illuminate\Http\Response<string(Hello), int(200), array<mixed>>'],
    ["response()->download(base_path('/tmp/wow.txt'))", 'Symfony\Component\HttpFoundation\BinaryFileResponse<unknown, int(200), array<mixed>, string(attachment)>', [
        'mimeType' => 'text/plain',
        'contentDisposition' => 'attachment; filename=wow.txt',
    ]],
    ["response()->download('/tmp/wow.txt')", 'Symfony\Component\HttpFoundation\BinaryFileResponse<string(/tmp/wow.txt), int(200), array<mixed>, string(attachment)>', [
        'mimeType' => 'text/plain',
        'contentDisposition' => 'attachment; filename=wow.txt',
    ]],
    ["response()->download('/tmp/wow.txt', headers: ['Content-type' => 'application/json'])", 'Symfony\Component\HttpFoundation\BinaryFileResponse<string(/tmp/wow.txt), int(200), array{Content-type: string(application/json)}, string(attachment)>', [
        'mimeType' => 'application/json',
        'contentDisposition' => 'attachment; filename=wow.txt',
    ]],
    ["response()->file('/tmp/wow.txt')", 'Symfony\Component\HttpFoundation\BinaryFileResponse<string(/tmp/wow.txt), int(200), array<mixed>, null>', [
        'mimeType' => 'text/plain',
        'contentDisposition' => null,
    ]],
]);

it('infers response creation', function (string $expression, string $expectedType) {
    $type = getExpressionType($expression);

    expect($type->toString())->toBe($expectedType);
})->with([
    ["new Illuminate\Http\Response", 'Illuminate\Http\Response<string(), int(200), array<mixed>>'],
    ["new Illuminate\Http\Response('')", 'Illuminate\Http\Response<string(), int(200), array<mixed>>'],
    ["new Illuminate\Http\JsonResponse(['data' => 1])", 'Illuminate\Http\JsonResponse<array{data: int(1)}, int(200), array<mixed>>'],
    ["new Illuminate\Http\JsonResponse(['data' => 1], 201, ['x-foo' => 'bar'])", 'Illuminate\Http\JsonResponse<array{data: int(1)}, int(201), array{x-foo: string(bar)}>'],
]);

function getExpressionType(string $expression)
{
    return getStatementType($expression);
}
