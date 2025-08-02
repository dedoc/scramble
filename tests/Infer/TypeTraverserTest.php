<?php

namespace Dedoc\Scramble\Tests\Infer;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeTraverser;

test('mutates type', function () {
    $traverser = new TypeTraverser([
        new class
        {
            public function enter(Type $type) {}

            public function leave(Type $type)
            {
                if ($type instanceof ObjectType && $type->name === 'replace_me') {
                    return new LiteralStringType('replaced');
                }

                return null;
            }
        },
    ]);

    $type = new Generic('self', [
        new ObjectType('replace_me'),
    ]);

    $traverser->traverse($type);
});
