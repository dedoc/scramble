<?php

use Dedoc\Scramble\Diagnostics\PhpDoc\Pd001RedundantTypeAnnotationDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context);
});

it('reports PD001 when @var repeats an inferred type', function () {
    $docNode = PhpDoc::parse('/** @var string */');
    $docNode->setAttribute('sourceClass', UserResource_Pd001RedundantTypeAnnotationDiagnosticTest::class);

    $item = new ArrayItemType_('name', new StringType);
    $item->setAttribute('docNode', $docNode);

    $this->transformer->transform($item);

    expect($this->context->diagnostics->diagnostics)->toHaveCount(1)
        ->and($this->context->diagnostics->diagnostics->first())->toBeInstanceOf(Pd001RedundantTypeAnnotationDiagnostic::class)
        ->and($this->context->diagnostics->diagnostics->first()->context())->toBe(__FILE__);
});
class UserResource_Pd001RedundantTypeAnnotationDiagnosticTest {}

it('renders PD001 output when analyzing documentation', function () {
    Scramble::configure()->withDocumentTransformers(function (OpenApi $_, OpenApiContext $context) {
        $docNode = PhpDoc::parse('/** @var string */');
        $docNode->setAttribute('sourceClass', UserResource_Pd001RedundantTypeAnnotationDiagnosticTest::class);
        $docNode->setAttribute('sourceLine', 35);

        $item = new ArrayItemType_('name', new StringType);
        $item->setAttribute('docNode', $docNode);

        app()->make(TypeTransformer::class, [
            'context' => $context,
        ])->transform($item);
    });

    $previousColumns = getenv('COLUMNS');
    putenv('COLUMNS=1000');

    try {
        $exitCode = Artisan::call('scramble:analyze');
        $output = Artisan::output();
    } finally {
        $previousColumns === false
            ? putenv('COLUMNS')
            : putenv("COLUMNS={$previousColumns}");
    }

    //    $expectedDiagnosticOutput = <<<EOL
    //    [PD001] Redundant `@var` type annotation on array item [`name`]: the type is already inferred as [`string`].
    //    Tip: Remove the `@var` type annotation and keep the description, `@format`, `@example`, or other tags if needed. Scramble infers the type from the expression automatically.
    //    Docs: https://scramble.dedoc.co/errors#pd001
    // EOL;

    dd($output);

    $expectedDiagnosticOutput = <<<'EOL'
  --> line 40 [PD001] redundant `@var string` annotations
    40 |             /**
    41 |              * @var string
       |                     ^^^^^^ inferred as `string`
    42 |              */
    43 |              'field' => $this->resource->field,

        Help: remove the redundant `@var` annotations, but keep other (`@example`, `@format`, description, etc.).
        Docs: https://scramble.dedoc.co/errors#pd001
EOL;

    $expectedDiagnosticOutput = str_replace('__FILE__', __FILE__, $expectedDiagnosticOutput);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain($expectedDiagnosticOutput);
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
