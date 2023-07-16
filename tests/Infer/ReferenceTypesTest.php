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
EOD)->getClassDefinition('Foo');

    expect($type->methods['bar']->type->toString())
        ->toBe('(): int(2)')
        ->and($type->methods['foo']->type->toString())
        ->toBe('(): int(2)');
});

it('resolves a cyclic reference safely', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        if (piu()) {
            return 1;
        }
        return $this->foo();
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('(): unknown|int(1)');
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
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): array{0: int(2), 1: int(2)}');
});

it('resolves unknown references to unknowns in non-reference return types', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return [$this->two(), $this->three()];
    }
    public function two () {
        return 2;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): array{0: int(2), 1: unknown}');
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
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): int(2)');
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
EOD)->getFunctionDefinition('foo');

    expect($type->type->toString())->toBe('(): int(2)');
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
EOD)->getClassDefinition('PendingUnknownWithSelfReference');

    expect($type->methods['returnSomeCall']->type->toString())
        ->toBe('(): unknown')
        ->and($type->methods['returnThis']->type->toString())
        ->toBe('(): self');
});

it('resolves deep references in unknowns after traversal', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo
{
    public function returnSomeCall()
    {
        if ($a) {
            return call_user_func($erw);
        }
        return (new Bar)->some()->call();
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['returnSomeCall']->type->toString())
        ->toBe('(): unknown');
});
