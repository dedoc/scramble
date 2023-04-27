<?php

it('track property assignment to in method', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function foo ($a) {
        $this->prop = $a;
        return $this;
    }
}

$a = (new Foo)->foo(123);
EOD)->getVarType('a');

    expect($type->getPropertyFetchType('prop')->toString())->toBe('int(123)');
});
