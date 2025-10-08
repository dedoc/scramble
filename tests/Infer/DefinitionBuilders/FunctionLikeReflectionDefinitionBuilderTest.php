<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;

test('builds the definition for is_null', function () {
    $def = (new FunctionLikeReflectionDefinitionBuilder('is_null'))->build();

    expect($def->type->toString())->toBe('(null|mixed): boolean');
});
