<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
});

it('distributes examples to oneOf items when multiple examples provided', function () {
    $transformer = new TypeTransformer(app(Infer::class), $this->context, []);

    $union = new Union([
        new StringType(),
        new IntegerType(),
    ]);

    $docNode = new PhpDocNode([
        new PhpDocTagNode('@example', new GenericTagValueNode('123456-1234-4321-123456')),
        new PhpDocTagNode('@example', new GenericTagValueNode('1234')),
    ]);

    $property = new ArrayItemType_('external_id', $union);
    $property->setAttribute('docNode', $docNode);

    $openApiType = $transformer->transform($property);

    expect($openApiType->toArray())->toMatchArray([
        'oneOf' => [
            [
                'type' => 'string',
                'example' => '123456-1234-4321-123456',
            ],
            [
                'type' => 'integer',
                'example' => 1234,
            ],
        ],
    ]);
});

it('distributes rich examples to oneOf items using type when multiple examples provided', function () {
    $transformer = new TypeTransformer(app(Infer::class), $this->context, []);

    $union = new Union([
        new StringType(),
        new IntegerType(),
    ]);

    $docNode = new PhpDocNode([
        new PhpDocTagNode('@example', new GenericTagValueNode(json_encode([
            'type' => 'string',
            'summary' => 'UUID Example',
            'description' => 'This is a UUID',
            'value' => '123456-1234-4321-123456',
        ]))),
        new PhpDocTagNode('@example', new GenericTagValueNode(json_encode([
            'type' => 'integer',
            'summary' => 'Integer Example',
            'value' => 1234,
        ]))),
    ]);

    $property = new ArrayItemType_('external_id', $union);
    $property->setAttribute('docNode', $docNode);

    $openApiType = $transformer->transform($property);

    expect($openApiType->toArray())->toMatchArray([
        'oneOf' => [
            [
                'type' => 'string',
                'description' => 'UUID Example',
                'example' => '123456-1234-4321-123456',
            ],
            [
                'type' => 'integer',
                'description' => 'Integer Example',
                'example' => 1234,
            ],
        ],
    ]);
});

it('keeps anyOf when only one example provided', function () {
    $transformer = new TypeTransformer(app(Infer::class), $this->context, []);

    $union = new Union([
        new StringType(),
        new IntegerType(),
    ]);

    $docNode = new PhpDocNode([
        new PhpDocTagNode('@example', new GenericTagValueNode('"123456-1234-4321-123456"')),
    ]);

    $property = new ArrayItemType_('external_id', $union);
    $property->setAttribute('docNode', $docNode);

    $openApiType = $transformer->transform($property);

    expect($openApiType->toArray())->toMatchArray([
        'anyOf' => [
            [
                'type' => 'string',
                'example' => '123456-1234-4321-123456',
            ],
            [
                'type' => 'integer',
            ],
        ],
    ]);
});

it('broadens type based on examples when @var is missing', function () {
    $context = new \Dedoc\Scramble\OpenApiContext(
        new \Dedoc\Scramble\Support\Generator\OpenApi('3.1.0'),
        new \Dedoc\Scramble\GeneratorConfig
    );
    $transformer = new \Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\PhpDocSchemaTransformer(
        app()->make(\Dedoc\Scramble\Support\Generator\TypeTransformer::class, ['context' => $context])
    );

    $type = new \Dedoc\Scramble\Support\Generator\Types\StringType;
    $docNode = new \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode([
        new \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode('@example', new \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode('{"type": "int", "value": 123}')),
        new \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode('@example', new \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode('{"type": "string", "value": "string"}')),
    ]);

    $transformedType = $transformer->transform($type, $docNode);

    expect($transformedType)->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Combined\OneOf::class);
    expect($transformedType->toArray()['oneOf'])->toBe([
        ['type' => 'integer', 'example' => 123],
        ['type' => 'string', 'example' => 'string'],
    ]);
});

it('supports bool and int as aliases in examples', function () {
    $context = new \Dedoc\Scramble\OpenApiContext(
        new \Dedoc\Scramble\Support\Generator\OpenApi('3.1.0'),
        new \Dedoc\Scramble\GeneratorConfig
    );
    $transformer = new \Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\PhpDocSchemaTransformer(
        app()->make(\Dedoc\Scramble\Support\Generator\TypeTransformer::class, ['context' => $context])
    );

    $type = new \Dedoc\Scramble\Support\Generator\Types\StringType;
    $docNode = new \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode([
        new \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode('@example', new \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode('{"type": "int", "value": 123}')),
        new \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode('@example', new \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode('{"type": "bool", "value": true}')),
    ]);

    $transformedType = $transformer->transform($type, $docNode);

    expect($transformedType->toArray()['oneOf'])->toBe([
        ['type' => 'integer', 'example' => 123],
        ['type' => 'boolean', 'example' => true],
    ]);
});
