<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypePath;
use Dedoc\Scramble\Support\Type\TypePathSet;
use Dedoc\Scramble\Support\Type\Union;

test('finds type path in union', function () {
    $type = new FunctionType('{closure}', [], new LiteralIntegerType(42));

    $templateContainingType = Union::wrap([
        $template = new TemplateType('T'),
        new FunctionType('{closure}', [], $template),
    ]);

    $path = TypePathSet::find(
        $templateContainingType,
        fn ($t) => $t === $template,
    );

    expect($path?->getFrom($type)?->toString())->toBe('int(42)');
});

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
