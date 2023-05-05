<?php

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
    // Resolves
    ['$this->bar()', 'int(1)'],
    ['$this->wow()', '(#self).wow()'],
    ['$this->bar()->wow()', '(#self).wow()', 'bar() { return $this; }'],
    ['$this->bar(new Baz())', '(new Baz)()', 'bar($a) { return $a; }'],
    ['$this->bar()->baz()', '(#(new Baz)()).baz()', 'bar() { return new Baz; }'],
    // Doesn't resolve as no need in resolution
    ['$this->wow($this->bar())', '(#self).wow((#self).bar())'],
    ['new Baz($this->bar())', '(new Baz)((#self).bar())'],
]);

it('resolves templates templates', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo($q) {
        return $q;
    }
    public function bar() {
        return $this->foo(fn ($q) => $q);
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['bar']->type->toString())->toBe('(): <TQ>(TQ): TQ');
});
