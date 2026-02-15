<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\RuleTransformers\EnumRule;
use Dedoc\Scramble\RuleTransformers\InRule;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\RuleSetToSchemaTransformer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
});

enum Enum_RuleSetToSchemaTransformerTest: string
{
    case FOO = 'foo';
    case BAR = 'bar';
}
