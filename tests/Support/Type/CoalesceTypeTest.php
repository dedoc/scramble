<?php

namespace Dedoc\Scramble\Tests\Support\Type;

test('resolves coalesce types with method call', function () {
    $type = analyzeFile(<<<'EOD'
<?php

class Foo {
    public function fooOrNull (): self|null {}
    public function bar (): string {}
}
EOD)->getExpressionType('(new Foo)->fooOrNull()->bar() ?? 42');

    expect($type->toString())->toBe('string');
});

test('resolves coalesce types with property fetch', function () {
    $type = analyzeFile(<<<'EOD'
<?php

class Foo {
    public int $barProp = 1;
    public function fooOrNull (): static|null {}
}
EOD)->getExpressionType('(new Foo)->fooOrNull()->barProp ?? "foo"');

    expect($type->toString())->toBe('int(1)|string(foo)');
});

test('resolves coalesce types with property fetch on non-object', function () {
    $type = analyzeFile('<?php class Foo { public int $barProp = 1; }')
        ->getExpressionType('(1)->barProp ?? "foo"');

    expect($type->toString())->toBe('string(foo)');
});
