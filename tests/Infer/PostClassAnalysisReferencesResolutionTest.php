<?php

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
