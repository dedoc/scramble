<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\ValidatedInput;
use Symfony\Component\HttpFoundation\InputBag;

it('preserves request provenance for zero-argument input access', function (string $code, string $expectedType, bool $mayContainRequestInput) {
    $type = getExpressionType($code, variables: [
        'request' => (new ObjectType(Request::class))->mergeAttributes(['mayContainRequestInput' => true]),
        'formRequest' => (new ObjectType(FormRequest::class))->mergeAttributes(['mayContainRequestInput' => true]),
    ]);

    expect($type->toString())
        ->toBe($expectedType)
        ->and($type->getAttribute('mayContainRequestInput') === true)
        ->toBe($mayContainRequestInput);
})->with([
    ['$request->all()', 'array<int|string, mixed>', true],
    ['$request->input()', 'array<int|string, mixed>', true],
    ['$request->post()', 'array<int|string, mixed>', true],
    ['$request->toArray()', 'array<int|string, mixed>', true],
    ['$request->json()', InputBag::class, true],
    ['$request->json()->all()', 'array<int|string, mixed>', true],
    ['$formRequest->validated()', 'array<int|string, mixed>', true],
    ['$formRequest->safe()', ValidatedInput::class, true],
    ['$formRequest->safe()->all()', 'array<int|string, mixed>', true],
    ['$request->input("foo", 42)', 'mixed', false],
    ['$request->post("foo", 42)', 'string|array<mixed>|null', false],
    ['$request->json("foo", 42)', 'unknown', false],
    ['$formRequest->validated("foo", 42)', 'mixed', false],
]);
