<?php

it('adfasdf pending self reference after analysis', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Bar {
    public function bar() {
        return $this;
    }
}
class Foo {
    public function foo() {
        return (new Bar)->bar();
    }
}
EOD, shouldResolveReferences: false)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('(): int(1)')
        ->and($type->methods['bar']->type->toString())
        ->toBe('(): int(1)');
})->skip('asdf');

it('resolves not ready self references after analysis', function (string $expression, string $expectedType, string $barFn = 'bar() { return 1; }') {
    $type = analyzeFile(sprintf(<<<'EOD'
<?php
class Foo {
    public function foo() {
        return %s;
    }
    public function %s
}
EOD, $expression, $barFn), shouldResolveReferences: false)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): '.$expectedType);
})->with([
    ['$this->bar()', 'int(1)'],
    ['new Baz($this->bar())', '(new Baz)(int(1))'],
    ['$this->wow()', '(#self).wow()'],
    ['$this->bar()->wow()', '(#self).wow()', 'bar() { return $this; }'],
    ['$this->bar(new Baz())', '(new Baz)()', 'bar($a) { return $a; }'],
    ['$this->bar(new Baz())', '(#(new Baz)()).wow()', 'bar($a) { return $a->wow(); }'],
]);


it('resolves all pending self references after sdf', function () {
    $type = /*analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo() {
        return $this->bar();
    }
    public function bar() {
        return 1;
    }
}
EOD)*/analyzeFile((new ReflectionClass(\Illuminate\Database\Eloquent\Model::class))->getFileName())->getClassDefinition('Foo');

    dd($type);

    expect($type->methods['foo']->type->toString())->toBe('(): self');
})->skip('af');
