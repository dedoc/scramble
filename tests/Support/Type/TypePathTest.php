<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\TypePath;

it('finds type', function () {
    $type = getStatementType(<<<'EOD'
['a' => fn (int $b) => 123]
EOD);

    $path = TypePath::findFirst(
        $type,
        fn ($t) => $t instanceof LiteralIntegerType,
    );

    expect($path?->getFrom($type)->toString())->toBe('int(123)');
});
