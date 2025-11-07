<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\ContainerUtils;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RuleSetToSchemaTransformer;
use Illuminate\Validation\Rule;

beforeEach(function () {
    $this->openApiTransformer = app(TypeTransformer::class, [
        'context' => new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig),
    ]);
    $context = ContainerUtils::makeContextable(RuleTransformerContext::class, [
        'field' => 'foo',
        OpenApi::class => $this->openApiTransformer->context->openApi,
    ]);
    $this->transformer = new RuleSetToSchemaTransformer(
        $this->openApiTransformer,
        $context,
    );
});

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

enum Enum_RuleSetToSchemaTransformerTest: string
{
    case FOO = 'foo';
    case BAR = 'bar';
}
