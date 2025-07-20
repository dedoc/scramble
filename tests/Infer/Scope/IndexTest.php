<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Index;

it('doesnt fail on internal class definition request', function () {
    $index = new Index;

    $def = $index->getClassDefinition(\Error::class);

    expect($def)->toBeInstanceOf(ClassDefinition::class);
});
