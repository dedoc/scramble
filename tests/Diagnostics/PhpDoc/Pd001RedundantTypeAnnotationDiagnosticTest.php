<?php

use Dedoc\Scramble\Diagnostics\PhpDoc\Pd001RedundantTypeAnnotationDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\UnknownType;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context);
});

it('reports PD001 when @var repeats an inferred type', function () {
    $docNode = PhpDoc::parse('/** @var string */');
    $docNode->setAttribute('sourceClass', 'App\\UserResource');

    $item = new ArrayItemType_('name', new StringType);
    $item->setAttribute('docNode', $docNode);

    $this->transformer->transform($item);

    expect($this->context->diagnostics->diagnostics)->toHaveCount(1)
        ->and($this->context->diagnostics->diagnostics->first())->toBeInstanceOf(Pd001RedundantTypeAnnotationDiagnostic::class)
        ->and($this->context->diagnostics->diagnostics->first()->context())->toBe('App\\UserResource');
});

it('does not report PD001 when @var adds information', function () {
    $item = new ArrayItemType_('error', new UnknownType);
    $item->setAttribute('docNode', PhpDoc::parse('/** @var array{msg: string, code: int} */'));

    $this->transformer->transform($item);

    expect($this->context->diagnostics->diagnostics)->toBeEmpty();
});

it('does not report PD001 when @var types array values that are inferred as unknown', function () {
    $inferred = new KeyedArrayType([
        new ArrayItemType_('foo', new UnknownType),
        new ArrayItemType_('bar', new UnknownType),
    ]);

    $item = new ArrayItemType_('payload', $inferred);
    $item->setAttribute('docNode', PhpDoc::parse('/** @var array{foo?: string, bar?: string} */'));

    $this->transformer->transform($item);

    expect($this->context->diagnostics->diagnostics)->toBeEmpty();
});

it('reports PD001 for @var string on cast-inferred datetimes', function () {
    $inferred = tap(new StringType, fn (StringType $t) => $t->setAttribute('format', 'date-time'));

    $item = new ArrayItemType_('created_at', $inferred);
    $item->setAttribute('docNode', PhpDoc::parse('/** @var string */'));

    $this->transformer->transform($item);

    expect($this->context->diagnostics->diagnostics)->toHaveCount(1)
        ->and($this->context->diagnostics->diagnostics->first())->toBeInstanceOf(Pd001RedundantTypeAnnotationDiagnostic::class);
});
