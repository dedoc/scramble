<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\StringType as InferStringType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\StreamedResponseToSchema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        StreamedResponseToSchema::class,
    ]);
});

it('transforms json inferred type to response', function () {
    $type = getStatementType("response()->streamJson(['foo' => 'bar'])");

    $response = $this->transformer->toResponse($type);

    expect($response->toArray())->toBe([
        'description' => '',
        'content' => [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => ['foo' => ['type' => 'string', 'const' => 'bar']],
                    'required' => ['foo'],
                ],
            ],
        ],
        'headers' => [
            'Transfer-Encoding' => [
                'required' => true,
                'schema' => ['type' => 'string', 'enum' => ['chunked']],
            ],
        ],
    ]);
});

it('transforms SSE inferred type to response', function () {
    $type = getStatementType('response()->eventStream(fn () => [])');

    $response = $this->transformer->toResponse($type);

    expect($response->toArray())->toBeSameJson([
        'description' => 'A server-sent events (SSE) streamed response. `</stream>` update will be sent to the event stream when the stream is complete.',
        'content' => [
            'text/event-stream' => [
                'schema' => [
                    'type' => 'object',
                    'examples' => [
                        "event: update\ndata: {data}\n\nevent: update\ndata: </stream>\n\n",
                    ],
                    'properties' => [
                        'event' => ['type' => 'string', 'examples' => ['update']],
                        'data' => (object) [],
                    ],
                    'required' => ['event', 'data'],
                ],
            ],
        ],
        'headers' => [
            'Transfer-Encoding' => [
                'required' => true,
                'schema' => ['type' => 'string', 'enum' => ['chunked']],
            ],
        ],
    ]);
});

it('transforms SSE without string end event to response', function () {
    $type = getStatementType("response()->eventStream(fn () => [], endStreamWith: new \Illuminate\Http\StreamedEvent(event: 'end', data: 'real'))");

    $response = $this->transformer->toResponse($type);

    expect($response->toArray())->toBeSameJson([
        'description' => 'A server-sent events (SSE) streamed response.',
        'content' => [
            'text/event-stream' => [
                'schema' => [
                    'type' => 'object',
                    'examples' => [
                        "event: update\ndata: {data}\n\n",
                    ],
                    'properties' => [
                        'event' => ['type' => 'string', 'examples' => ['update']],
                        'data' => (object) [],
                    ],
                    'required' => ['event', 'data'],
                ],
            ],
        ],
        'headers' => [
            'Transfer-Encoding' => [
                'required' => true,
                'schema' => ['type' => 'string', 'enum' => ['chunked']],
            ],
        ],
    ]);
});

it('transforms plain streamed type to response', function () {
    $type = getStatementType('response()->stream(fn () => f())');

    $response = $this->transformer->toResponse($type);

    expect($response->toArray())->toBeSameJson([
        'description' => '',
        'content' => [
            'text/html' => [
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ],
        'headers' => [
            'Transfer-Encoding' => [
                'required' => true,
                'schema' => ['type' => 'string', 'enum' => ['chunked']],
            ],
        ],
    ]);
});

it('documents a streamed download with a binary content type', function () {
    $type = new Generic(StreamedResponse::class, [
        new InferStringType,
        new LiteralIntegerType(200),
        new KeyedArrayType([
            new ArrayItemType_(
                'Content-Type',
                new LiteralStringType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ),
        ]),
    ]);

    $response = $this->transformer->toResponse($type);

    expect($response->toArray())->toBeSameJson([
        'description' => '',
        'content' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                'schema' => [
                    'type' => 'string',
                    'format' => 'binary',
                ],
            ],
        ],
        'headers' => [
            'Transfer-Encoding' => [
                'required' => true,
                'schema' => ['type' => 'string', 'enum' => ['chunked']],
            ],
        ],
    ]);
});

it('documents Storage streamed file responses as binary', function (string $expression) {
    $type = getStatementType($expression);

    expect($type->toString())->toBe(StreamedResponse::class.'<string, int(200), array{Content-Type: string(application/vnd.openxmlformats-officedocument.spreadsheetml.sheet)}>');

    $response = $this->transformer->toResponse($type);

    expect($response->toArray())->toBeSameJson([
        'description' => '',
        'content' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                'schema' => [
                    'type' => 'string',
                    'format' => 'binary',
                ],
            ],
        ],
        'headers' => [
            'Transfer-Encoding' => [
                'required' => true,
                'schema' => ['type' => 'string', 'enum' => ['chunked']],
            ],
        ],
    ]);
})->with([
    'download' => Storage::class."::download(\$pathToSpreadSheet, 'device_export_'.now()->format('Ymd_His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])",
    'disk download' => Storage::class."::disk()->download(\$pathToSpreadSheet, 'device_export_'.now()->format('Ymd_His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])",
    'response' => Storage::class."::response(\$pathToSpreadSheet, 'device_export_'.now()->format('Ymd_His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])",
    'serve' => Storage::class."::serve(\$request, \$pathToSpreadSheet, 'device_export_'.now()->format('Ymd_His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])",
]);

it('infers Storage response MIME type from file arguments', function (string $expression, string $contentType) {
    $type = getStatementType($expression);

    expect($type->getAttribute('mimeType'))->toBe($contentType);

    $content = $this->transformer->toResponse($type)->toArray()['content'];

    expect($content)->toHaveKey($contentType)
        ->and($content[$contentType]['schema'])->toBe([
            'type' => 'string',
            'format' => 'binary',
        ]);
})->with([
    'path' => [
        Storage::class."::download('exports/report.xlsx')",
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],
    'name fallback' => [
        Storage::class."::download(\$path, 'report.pdf')",
        'application/pdf',
    ],
]);

it('prefers an explicit Storage response content type over the file extension', function () {
    $type = getStatementType(
        Storage::class."::download('exports/report.xlsx', headers: ['Content-Type' => 'text/csv'])",
    );

    $content = $this->transformer->toResponse($type)->toArray()['content'];

    expect($content)->toBe([
        'text/csv' => [
            'schema' => ['type' => 'string'],
        ],
    ]);
});
