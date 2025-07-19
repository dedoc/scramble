<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\BinaryFileResponseToSchema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        BinaryFileResponseToSchema::class,
    ]);
});

it('transforms basic inferred type to response', function () {
    // $type = getStatementType("response()->download(base_path('/tmp/wow.txt'))");
    $type = (new Generic(BinaryFileResponse::class, [
        new UnknownType,
        new LiteralIntegerType(200),
        new ArrayType,
        new LiteralStringType('attachment'),
    ]))->mergeAttributes([
        'mimeType' => 'text/plain',
        'contentDisposition' => 'attachment; filename=wow.txt',
    ]);

    $response = $this->transformer->toResponse($type);

    expect($response->headers)->toHaveKey('Content-Disposition')
        ->and($response->headers['Content-Disposition']->example)->toBe('attachment; filename=wow.txt')
        ->and($response->content)->toHaveKey('text/plain')
        ->and($response->getContent('text/plain')->toArray())->toBe(['type' => 'string']);
});
