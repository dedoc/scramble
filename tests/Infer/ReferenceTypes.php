<?php

/*
 * Reference types are the types which are created when there is no available info at the moment
 * of nodes traversal. Later, after the fn or class is traversed, references are resolved.
 */

/*
 * References in own class.
 */
it('resolves a reference when encountered in self class', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return $this->bar();
    }
    public function bar () {
        return 2;
    }
}
EOD)->getClassType('Foo');

    expect($type->getMethodType('bar')->toString())
        ->toBe('(): int(2)')
        ->and($type->getMethodType('foo')->toString())
        ->toBe('(): int(2)');
});

it('resolves references in non-reference return types', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return [$this->two(), $this->two()];
    }
    public function two () {
        return 2;
    }
}
EOD)->getClassType('Foo');

    expect($type->getMethodType('foo')->toString())->toBe('(): array{0: int(2), 1: int(2)}');
});

it('resolves a deep reference when encountered in self class', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return $this->bar()->bar()->two();
    }
    public function bar () {
        return $this;
    }
    public function two () {
        return 2;
    }
}
EOD)->getClassType('Foo');

    expect($type->getMethodType('foo')->toString())->toBe('(): int(2)');
});

it('resolves a reference from function', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo () {
    return bar();
}
function bar () {
    return 2;
}
EOD)->getFunctionType('foo');

    expect($type->toString())->toBe('(): int(2)');
});

it('resolves references in unknowns after traversal', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class PendingUnknownWithSelfReference
{
    public function returnSomeCall()
    {
        return some();
    }

    public function returnThis()
    {
        return $this;
    }
}
EOD)->getClassType('PendingUnknownWithSelfReference');

    expect($type->methods['returnSomeCall']->toString())
        ->toBe('(): unknown')
        ->and($type->methods['returnThis']->toString())
        ->toBe('(): PendingUnknownWithSelfReference');
});
