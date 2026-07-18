<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\RuleTransformers\AcceptedRule;
use Dedoc\Scramble\RuleTransformers\EnumRule;
use Dedoc\Scramble\RuleTransformers\InRule;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\RuleSetToSchemaTransformer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

beforeEach(function () {
    $this->openApiTransformer = app(TypeTransformer::class, [
        'context' => new OpenApiContext(new OpenApi('3.1.0'), $config = new GeneratorConfig),
    ]);

    $this->transformer = new RuleSetToSchemaTransformer(
        $this->openApiTransformer,
        $config->ruleTransformers,
    );
});

describe(EnumRule::class, function () {
    test('enum rule', function () {
        $rules = [Rule::enum(Enum_RuleSetToSchemaTransformerTest::class)];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe(['$ref' => '#/components/schemas/Enum_RuleSetToSchemaTransformerTest'])
            ->and($this->openApiTransformer->getComponents()->getSchema('Enum_RuleSetToSchemaTransformerTest')->toArray())
            ->toBe([
                'type' => 'string',
                'enum' => ['foo', 'bar'],
            ]);
    });

    test('enum rule with only', function () {
        $rules = [
            Rule::enum(Enum_RuleSetToSchemaTransformerTest::class)->only([
                Enum_RuleSetToSchemaTransformerTest::FOO,
            ]),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'const' => 'foo',
            ]);
    })->skip(! method_exists(Enum::class, 'only'));

    test('enum rule with only keeps case descriptions', function () {
        config()->set('scramble.enum_cases_description_strategy', 'description');

        $rules = [
            Rule::enum(DocumentedEnum_RuleSetToSchemaTransformerTest::class)->only([
                DocumentedEnum_RuleSetToSchemaTransformerTest::FOO,
                DocumentedEnum_RuleSetToSchemaTransformerTest::BAR,
            ]),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe([
            'type' => 'string',
            'description' => <<<'EOF'
| |
|---|
| `foo` <br/> Foo case description. |
| `bar` <br/> Bar case description. |
EOF,
            'enum' => ['foo', 'bar'],
        ]);
    })->skip(! method_exists(Enum::class, 'only'));

    test('enum rule with except', function () {
        $rules = [
            Rule::enum(Enum_RuleSetToSchemaTransformerTest::class)->except([
                Enum_RuleSetToSchemaTransformerTest::FOO,
            ]),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'const' => 'bar',
            ]);
    })->skip(! method_exists(Enum::class, 'except'));

    test('enum rule with except keeps case descriptions', function () {
        config()->set('scramble.enum_cases_description_strategy', 'description');

        $rules = [
            Rule::enum(DocumentedEnum_RuleSetToSchemaTransformerTest::class)->except([
                DocumentedEnum_RuleSetToSchemaTransformerTest::BAZ,
            ]),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe([
            'type' => 'string',
            'description' => <<<'EOF'
| |
|---|
| `foo` <br/> Foo case description. |
| `bar` <br/> Bar case description. |
EOF,
            'enum' => ['foo', 'bar'],
        ]);
    })->skip(! method_exists(Enum::class, 'except'));

    test('enum rule with only keeps case descriptions as extension', function () {
        config()->set('scramble.enum_cases_description_strategy', 'extension');

        $rules = [
            Rule::enum(DocumentedEnum_RuleSetToSchemaTransformerTest::class)->only([
                DocumentedEnum_RuleSetToSchemaTransformerTest::FOO,
                DocumentedEnum_RuleSetToSchemaTransformerTest::BAR,
            ]),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe([
            'type' => 'string',
            'enum' => ['foo', 'bar'],
            'x-enumDescriptions' => [
                'foo' => 'Foo case description.',
                'bar' => 'Bar case description.',
            ],
        ]);
    })->skip(! method_exists(Enum::class, 'only'));

    test('nullable enum rule', function () {
        $rules = ['nullable', new Enum(Enum_RuleSetToSchemaTransformerTest::class)];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe([
            'anyOf' => [
                ['$ref' => '#/components/schemas/Enum_RuleSetToSchemaTransformerTest'],
                ['type' => 'null'],
            ],
        ])
            ->and($this->openApiTransformer->getComponents()->getSchema('Enum_RuleSetToSchemaTransformerTest')->toArray())
            ->toBe([
                'type' => 'string',
                'enum' => ['foo', 'bar'],
            ]);
    });

    test('nullable enum rule with only', function () {
        $rules = [
            'nullable',
            (new Enum(Enum_RuleSetToSchemaTransformerTest::class))->only([
                Enum_RuleSetToSchemaTransformerTest::FOO,
            ]),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe([
            'type' => ['string', 'null'],
            'enum' => ['foo', null],
        ]);
    })->skip(! method_exists(Enum::class, 'only'));
});

describe(InRule::class, function () {
    test('in rule', function () {
        $rules = [Rule::in(['a', 'b'])];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'enum' => ['a', 'b'],
            ]);
    });

    test('in rule with array type places enum on items', function () {
        $rules = ['array', Rule::in(['a', 'b', 'c'])];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['a', 'b', 'c'],
                ],
            ]);
    });
});

describe(AcceptedRule::class, function () {
    test('accepted rule', function () {
        $rules = ['accepted'];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())->toBe([
            'enum' => ['yes', 'on', '1', 1, 'true', true],
        ]);
    });
});

describe(File::class, function () {
    test('file rule', function () {
        $rules = [File::types(['pdf'])->min(1024)->max(12 * 1024)];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'format' => 'binary',
                'contentMediaType' => 'application/octet-stream',
                'description' => 'File size must be between 1024 kilobytes and 12288 kilobytes.',
            ]);
    });

    test('file min rule', function ($rules) {
        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'format' => 'binary',
                'contentMediaType' => 'application/octet-stream',
                'description' => 'Minimum file size: 1024 kilobytes.',
            ]);
    })->with([
        'file first' => [['file', 'min:1024']],
        'min first' => [['min:1024', 'file']],
    ]);

    test('file max rule', function ($rules) {
        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'format' => 'binary',
                'contentMediaType' => 'application/octet-stream',
                'description' => 'Maximum file size: 4096 kilobytes.',
            ]);
    })->with([
        'file first' => [['file', 'max:4096']],
        'max first' => [['max:4096', 'file']],
    ]);

    test('file size rule', function ($rules) {
        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'format' => 'binary',
                'contentMediaType' => 'application/octet-stream',
                'description' => 'File size must be 2048 kilobytes.',
            ]);
    })->with([
        'file first' => [['file', 'size:2048']],
        'size first' => [['size:2048', 'file']],
    ]);

    test('file between rule', function ($rules) {
        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'format' => 'binary',
                'contentMediaType' => 'application/octet-stream',
                'description' => 'File size must be between 1024 kilobytes and 4096 kilobytes.',
            ]);
    })->with([
        'file first' => [['file', 'between:1024,4096']],
        'between first' => [['between:1024,4096', 'file']],
    ]);

    test('image file rule', function () {
        $rules = [
            File::image()->dimensions(
                Rule::dimensions()->maxWidth(1000)->maxHeight(500),
            ),
        ];

        $schema = $this->transformer->transform($rules);

        expect($schema->toArray())
            ->toBe([
                'type' => 'string',
                'format' => 'binary',
                'contentMediaType' => 'application/octet-stream',
            ]);
    });
});

enum Enum_RuleSetToSchemaTransformerTest: string
{
    case FOO = 'foo';
    case BAR = 'bar';
}

enum DocumentedEnum_RuleSetToSchemaTransformerTest: string
{
    /**
     * Foo case description.
     */
    case FOO = 'foo';
    /**
     * Bar case description.
     */
    case BAR = 'bar';
    /**
     * Baz case description.
     */
    case BAZ = 'baz';
}
