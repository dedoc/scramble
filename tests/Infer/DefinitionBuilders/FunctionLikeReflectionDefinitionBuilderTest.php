<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Support\Type\TemplateType;
use ReflectionMethod;

test('builds the definition for is_null', function () {
    $def = (new FunctionLikeReflectionDefinitionBuilder('is_null'))->build();

    expect($def->type->toString())->toBe('(null|mixed): boolean');
});

test('builds the definition for phpstan-this-out', function () {
    $def = (new FunctionLikeReflectionDefinitionBuilder(
        'updateSelfOut',
        new ReflectionMethod(PhpstanThisOut_FunctionLikeReflectionDefinitionBuilderTest::class, 'updateSelfOut'),
        collect(['T' => new TemplateType('T')]),
    ))->build();

    expect($def->getSelfOutType()?->toString())->toBe('self<int>');
});

/**
 * @template T
 */
class PhpstanThisOut_FunctionLikeReflectionDefinitionBuilderTest
{
    /**
     * @return $this
     * @phpstan-this-out static<int>
     */
    public function updateSelfOut(): self
    {
        return $this;
    }
}
