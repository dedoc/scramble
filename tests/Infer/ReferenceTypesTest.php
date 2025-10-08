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

it('correctly replaces templates without modifying type', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo ($a) {
        return ['a' => $a];
    }
}
EOD);

    /*
     * Previously this test would fail due to original return type being mutated.
     */
    $type->getExpressionType('(new Foo)->foo(123)');

    expect($type->getExpressionType('(new Foo)->foo(42)')->toString())
        ->toBe('array{a: int(42)}');
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

it('resolves an indirect reference', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return $this->bar();
    }
    public function bar () {
        return $this->foo();
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('(): unknown');
});

it('resolves a cyclic reference introduced by template method call', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo($q)
    {
        return $q->wow();
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('<TQ>(TQ): unknown');
});

it('resolves a cyclic reference introduced by template property fetch', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo ()
    {
        return fn($q) => $q->prop;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('(): <TQ>(TQ): unknown');
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

    expect($type->methods['foo']->type->toString())->toBe('(): list{int(2), int(2)}');
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

    expect($type->methods['foo']->type->toString())->toBe('(): list{int(2), unknown}');
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
            return foobarfoo($erw);
        }
        return (new Bar)->some()->call();
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['returnSomeCall']->type->toString())
        ->toBe('(): unknown');
});

it('handles usage of type annotation when resolved inferred type is unknown', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo
{
    public function returnSomeCall()
    {
        return $this->bar();
    }

    public function bar(): SomeClass {
        return foo();
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['returnSomeCall']->type->toString())
        ->toBe('(): SomeClass');
});
