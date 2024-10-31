<?php

use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;

it('replaces a non-self referencing type in self referencing type', function () {
    $type = new Union([new IntegerType]);
    $type->types[] = $type;

    $replacedType = (new TypeWalker)->replace($type, fn ($t) => $t instanceof IntegerType ? new StringType : null);

    expect($replacedType->types[0])->toBeInstanceOf(StringType::class)
        ->and($replacedType->types[1])->toBe($replacedType);
});
