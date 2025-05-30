<?php

namespace Dedoc\Scramble\Tests\Infer;

class Bar
{
    public function __construct(
        public int $id,
    ) {}

    public function foo()
    {
        return $this->id;
    }
}

it('infers a return type of property fetch on an object', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function bar(\Dedoc\Scramble\Tests\Infer\Bar $object)
    {
        return $object->id;
    }
}
EOD)->getExpressionType('(new Foo)->bar()');

    expect($type->toString())->toBe('int');
});

it('infers a return type of property fetch on a created object', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function bar()
    {
        $object = new \Dedoc\Scramble\Tests\Infer\Bar(123);

        return $object->id;
    }
}
EOD)->getExpressionType('(new Foo)->bar()');

    expect($type->toString())->toBe('int(123)');
});

it('infers a return type of method call on an argument', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function bar(\Dedoc\Scramble\Tests\Infer\Bar $object)
    {
        return $object->foo();
    }
}
EOD)->getExpressionType('(new Foo)->bar()');

    expect($type->toString())->toBe('int');
});
