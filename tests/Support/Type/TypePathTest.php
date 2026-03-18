<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\TypePath;

it('finds type', function () {
    $type = getStatementType(<<<'PHP'
['a' => fn (int $b) => 123]
PHP);

    $path = TypePath::findFirst(
        $type,
        fn ($t) => $t instanceof LiteralIntegerType,
    );

    expect($path?->getFrom($type)->toString())->toBe('int(123)');
});

it('finds in array', function () {
    $type = getStatementType("['a' => 'foo', 'b' => 42]");

    $path = TypePath::findFirst(
        $type,
        fn ($t) => $t instanceof LiteralIntegerType,
    );

    expect($path?->getFrom($type)->toString())->toBe('int(42)');
});
