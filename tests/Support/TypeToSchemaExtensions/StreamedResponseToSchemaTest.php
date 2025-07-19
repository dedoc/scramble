<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\StreamedResponseToSchema;

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
                    'properties' => ['foo' => ['type' => 'string', 'enum' => ['bar']]],
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
                        'event' => ['type' => 'string', 'example' => 'update'],
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
                        'event' => ['type' => 'string', 'example' => 'update'],
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
